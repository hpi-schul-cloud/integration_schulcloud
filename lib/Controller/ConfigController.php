<?php
/**
 * Nextcloud - schulcloud
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Julien Veyssier <eneiluj@posteo.net>
 * @copyright Julien Veyssier 2020
 */

namespace OCA\Schulcloud\Controller;

use OCP\App\IAppManager;
use OCP\Files\IAppData;
use OCP\AppFramework\Http\DataDisplayResponse;

use OCP\IURLGenerator;
use OCP\IConfig;
use OCP\IServerContainer;
use OCP\IL10N;
use OCP\ILogger;

use OCP\AppFramework\Http;
use OCP\AppFramework\Http\RedirectResponse;

use OCP\AppFramework\Http\ContentSecurityPolicy;

use OCP\IRequest;
use OCP\IDBConnection;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Controller;
use OCP\Http\Client\IClientService;

use OCA\Schulcloud\AppInfo\Application;
use OCA\Schulcloud\Service\SchulcloudAPIService;

require_once __DIR__ . '/../constants.php';

class ConfigController extends Controller {


	private $userId;
	private $config;
	private $dbconnection;
	private $dbtype;

	public function __construct($AppName,
								IRequest $request,
								IServerContainer $serverContainer,
								IConfig $config,
								IAppManager $appManager,
								IAppData $appData,
								IDBConnection $dbconnection,
								IURLGenerator $urlGenerator,
								IL10N $l,
								ILogger $logger,
								IClientService $clientService,
								SchulcloudAPIService $schulcloudAPIService,
								$userId) {
		parent::__construct($AppName, $request);
		$this->l = $l;
		$this->userId = $userId;
		$this->appData = $appData;
		$this->serverContainer = $serverContainer;
		$this->config = $config;
		$this->dbconnection = $dbconnection;
		$this->urlGenerator = $urlGenerator;
		$this->logger = $logger;
		$this->clientService = $clientService;
		$this->schulcloudAPIService = $schulcloudAPIService;
	}

	/**
	 * set config values
	 * @NoAdminRequired
	 *
	 * @param array $values
	 * @return DataResponse
	 */
	public function setConfig(array $values): DataResponse {
		foreach ($values as $key => $value) {
			$this->config->setUserValue($this->userId, Application::APP_ID, $key, $value);
		}
		if (isset($values['token']) && $values['token'] === '') {
			$this->config->setUserValue($this->userId, Application::APP_ID, 'refresh_token', '');
		}
		$response = new DataResponse(1);
		return $response;
	}

	/**
	 * set admin config values
	 *
	 * @param array $values
	 * @return DataResponse
	 */
	public function setAdminConfig(array $values): DataResponse {
		foreach ($values as $key => $value) {
			$this->config->setAppValue(Application::APP_ID, $key, $value);
		}
		$response = new DataResponse(1);
		return $response;
	}

	/**
	 * receive oauth redirection forwarded by custom protocol handler
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 *
	 * @param string $url
	 * @return RedirectResponse
	 */
	public function oauthProtocolRedirect(string $url = ''): RedirectResponse {
		if ($url === '') {
			$message = $this->l->t('Error getting OAuth access token');
			return new RedirectResponse(
				$this->urlGenerator->linkToRoute('settings.PersonalSettings.index', ['section' => 'connected-accounts']) .
				'?schulcloudToken=error&message=' . urlencode($message) . '#schulcloud_prefs'
			);
		}
		$parts = parse_url($url);
		parse_str($parts['query'], $params);
		return $this->oauthRedirect($params['code'], $params['state']);
	}

	/**
	 * receive oauth redirection
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 *
	 * @param ?string $code
	 * @param ?string $state
	 * @return RedirectResponse
	 */
	public function oauthRedirect(?string $code = '', ?string $state = ''): RedirectResponse {
		$configState = $this->config->getUserValue($this->userId, Application::APP_ID, 'oauth_state', '');
		$clientID = DEFAULT_SCHULCLOUD_CLIENT_ID;
		$clientSecret = DEFAULT_SCHULCLOUD_CLIENT_SECRET;

		// anyway, reset state
		$this->config->setUserValue($this->userId, Application::APP_ID, 'oauth_state', '');

		if ($configState !== '' and $configState === $state) {
			$schulcloudUrl = $this->config->getUserValue($this->userId, Application::APP_ID, 'url', '');
			// TODO replace with protocol
			$redirect_uri = $this->urlGenerator->linkToRouteAbsolute('integration_schulcloud.config.oauthRedirect');
			$result = $this->schulcloudAPIService->requestOAuthAccessToken($schulcloudUrl, [
				'client_id' => $clientID,
				'client_secret' => $clientSecret,
				'code' => $code,
				'grant_type' => 'authorization_code',
				'redirect_uri' => $redirect_uri,
			], 'POST');
			if (isset($result['access_token']) && isset($result['refresh_token'])) {
				$accessToken = $result['access_token'];
				$refreshToken = $result['refresh_token'];
				$this->config->setUserValue($this->userId, Application::APP_ID, 'token', $accessToken);
				$this->config->setUserValue($this->userId, Application::APP_ID, 'refresh_token', $refreshToken);
				return new RedirectResponse(
					$this->urlGenerator->linkToRoute('settings.PersonalSettings.index', ['section' => 'connected-accounts']) .
					'?schulcloudToken=success#schulcloud_prefs'
				);
			}
			$result = $this->l->t('Error getting OAuth access token');
		}

		return new RedirectResponse(
			$this->urlGenerator->linkToRoute('settings.PersonalSettings.index', ['section' => 'connected-accounts']) .
			'?schulcloudToken=error&message=' . urlencode($result) . '#schulcloud_prefs'
		);
	}
}
