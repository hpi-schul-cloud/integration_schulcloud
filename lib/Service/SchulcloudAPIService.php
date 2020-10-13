<?php
/**
 * Nextcloud - schulcloud
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Julien Veyssier
 * @copyright Julien Veyssier 2020
 */

namespace OCA\Schulcloud\Service;

use OCP\IL10N;
use Psr\Log\LoggerInterface;
use OCP\Http\Client\IClientService;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ServerException;

class SchulcloudAPIService {

	private $l10n;
	private $logger;

	/**
	 * Service to make requests to Schulcloud v3 (JSON) API
	 */
	public function __construct (
		string $appName,
		LoggerInterface $logger,
		IL10N $l10n,
		IClientService $clientService
	) {
		$this->appName = $appName;
		$this->l10n = $l10n;
		$this->logger = $logger;
		$this->clientService = $clientService;
		$this->client = $clientService->newClient();
	}

	/**
	 * @param string $url
	 * @param string $accessToken
	 * @param string $refreshToken
	 * @param string $clientID
	 * @param string $clientSecret
	 * @param ?string $since
	 * @return array
	 */
	public function getNotifications(string $url,
									string $accessToken, string $refreshToken, string $clientID, string $clientSecret,
									?string $since): array {
		$result = $this->request($url, $accessToken, $refreshToken, $clientID, $clientSecret, 'notifications.json', $params);
		if (isset($result['error'])) {
			return $result;
		}
		$notifications = [];
		if (isset($result['notifications']) && is_array($result['notifications'])) {
			foreach ($result['notifications'] as $notification) {
				$notifications[] = $notification;
			}
		}

		return $notifications;
	}

	/**
	 * @param string $url
	 * @param string $accessToken
	 * @param string $refreshToken
	 * @param string $clientID
	 * @param string $clientSecret
	 * @param string $username
	 * @return ?string
	 */
	public function getSchulcloudAvatar(string $url,
										string $accessToken, string $refreshToken, string $clientID, string $clientSecret,
										string $username): ?string {
		$result = $this->request($url, $accessToken, $refreshToken, $clientID, $clientSecret, 'users/'.$username.'.json');
		if (isset($result['user'], $result['user']['avatar_template'])) {
			$avatarUrl = $url . str_replace('{size}', '32', $result['user']['avatar_template']);
			return $this->client->get($avatarUrl)->getBody();
		}
		return '';
	}

	/**
	 * @param string $url
	 * @param string $accessToken
	 * @param string $refreshToken
	 * @param string $clientID
	 * @param string $clientSecret
	 * @param string $endPoint
	 * @param array $params
	 * @param string $method
	 * @return array
	 */
	public function request(string $url,
							string $accessToken, string $refreshToken, string $clientID, string $clientSecret,
							string $endPoint, array $params = [], string $method = 'GET'): array {
		try {
			$url = $url . '/' . $endPoint;
			$options = [
				'headers' => [
					'User-Api-Key' => $accessToken,
					// optional
					//'User-Api-Client-Id' => $clientId,
					'User-Agent' => 'Nextcloud Schulcloud integration'
				],
			];

			if (count($params) > 0) {
				if ($method === 'GET') {
					// manage array parameters
					$paramsContent = '';
					foreach ($params as $key => $value) {
						if (is_array($value)) {
							foreach ($value as $oneArrayValue) {
								$paramsContent .= $key . '[]=' . urlencode($oneArrayValue) . '&';
							}
							unset($params[$key]);
						}
					}
					$paramsContent .= http_build_query($params);

					$url .= '?' . $paramsContent;
				} else {
					$options['body'] = $params;
				}
			}

			if ($method === 'GET') {
				$response = $this->client->get($url, $options);
			} else if ($method === 'POST') {
				$response = $this->client->post($url, $options);
			} else if ($method === 'PUT') {
				$response = $this->client->put($url, $options);
			} else if ($method === 'DELETE') {
				$response = $this->client->delete($url, $options);
			}
			$body = $response->getBody();
			$respCode = $response->getStatusCode();

			if ($respCode >= 400) {
				return ['error' => $this->l10n->t('Bad credentials')];
			} else {
				return json_decode($body, true);
			}
		} catch (ServerException | ClientException $e) {
			$response = $e->getResponse();
			$body = (string) $response->getBody();
			// refresh token if it's invalid and we are using oauth
			if (strpos($body, 'expired') !== false || $response->getStatusCode() === 401) {
				$this->logger->info('Trying to REFRESH the access token', ['app' => $this->appName]);
				// try to refresh the token
				$result = $this->requestOAuthAccessToken($url, [
					'client_id' => $clientID,
					'client_secret' => $clientSecret,
					'grant_type' => 'refresh_token',
					'refresh_token' => $refreshToken,
				], 'POST');
				if (isset($result['access_token'])) {
					$accessToken = $result['access_token'];
					$this->config->setUserValue($this->userId, Application::APP_ID, 'token', $accessToken);
					// retry the request with new access token
					return $this->request(
						$url, $accessToken, $refreshToken, $clientID, $clientSecret, $endPoint, $params, $method
					);
				}
			}
			$this->logger->warning('Schulcloud API error : '.$e, ['app' => $this->appName]);
			return ['error' => $e->getMessage()];
		}
	}

	/**
	 * @param string $url
	 * @param array $params
	 * @param string $method
	 * @return array
	 */
	public function requestOAuthAccessToken(string $url, array $params = [], string $method = 'GET'): array {
		try {
			$url = $url . '/oauth2/token';
			$options = [
				'headers' => [
					'User-Agent'  => 'Nextcloud Schulcloud integration',
				]
			];

			if (count($params) > 0) {
				if ($method === 'GET') {
					$paramsContent = http_build_query($params);
					$url .= '?' . $paramsContent;
				} else {
					$options['body'] = $params;
				}
			}

			if ($method === 'GET') {
				$response = $this->client->get($url, $options);
			} else if ($method === 'POST') {
				$response = $this->client->post($url, $options);
			} else if ($method === 'PUT') {
				$response = $this->client->put($url, $options);
			} else if ($method === 'DELETE') {
				$response = $this->client->delete($url, $options);
			}
			$body = $response->getBody();
			$respCode = $response->getStatusCode();

			if ($respCode >= 400) {
				return ['error' => $this->l10n->t('OAuth access token refused')];
			} else {
				return json_decode($body, true);
			}
		} catch (\Exception $e) {
			$this->logger->warning('Schulcloud OAuth error : '.$e, ['app' => $this->appName]);
			return ['error' => $e->getMessage()];
		}
	}
}
