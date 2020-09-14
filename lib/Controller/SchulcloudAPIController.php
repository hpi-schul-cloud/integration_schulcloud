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

use OCP\AppFramework\Http;
use OCP\AppFramework\Http\RedirectResponse;

use OCP\AppFramework\Http\ContentSecurityPolicy;

use OCP\ILogger;
use OCP\IRequest;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Controller;

use OCA\Schulcloud\Service\SchulcloudAPIService;
use OCA\Schulcloud\AppInfo\Application;

require_once __DIR__ . '/../constants.php';

class SchulcloudAPIController extends Controller {


	private $userId;
	private $config;
	private $dbconnection;
	private $dbtype;

	public function __construct($AppName,
								IRequest $request,
								IServerContainer $serverContainer,
								IConfig $config,
								IL10N $l10n,
								IAppManager $appManager,
								IAppData $appData,
								ILogger $logger,
								SchulcloudAPIService $schulcloudAPIService,
								$userId) {
		parent::__construct($AppName, $request);
		$this->userId = $userId;
		$this->l10n = $l10n;
		$this->appData = $appData;
		$this->serverContainer = $serverContainer;
		$this->config = $config;
		$this->logger = $logger;
		$this->schulcloudAPIService = $schulcloudAPIService;
		$this->accessToken = $this->config->getUserValue($this->userId, Application::APP_ID, 'token', '');
		$this->refreshToken = $this->config->getUserValue($this->userId, Application::APP_ID, 'refresh_token', '');
		$this->clientID = DEFAULT_SCHULCLOUD_CLIENT_ID;
		$this->clientSecret = DEFAULT_SCHULCLOUD_CLIENT_SECRET;
		$this->schulcloudUrl = $this->config->getUserValue($this->userId, Application::APP_ID, 'url', '');
	}

	/**
	 * get notification list
	 * @NoAdminRequired
	 */
	public function getSchulcloudUrl() {
		return new DataResponse($this->schulcloudUrl);
	}

	/**
	 * get schulcloud user avatar
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function getSchulcloudAvatar($username) {
		$response = new DataDisplayResponse(
			$this->schulcloudAPIService->getSchulcloudAvatar(
				$this->schulcloudUrl, $this->accessToken, $this->refreshToken, $this->clientID, $this->clientSecret, $username
			)
		);
		$response->cacheFor(60*60*24);
		return $response;
	}

	/**
	 * get todo list
	 * @NoAdminRequired
	 */
	public function getNotifications($since = null) {
		if ($this->accessToken === '' or $this->clientID === '') {
			return new DataResponse('', 400);
		}
		$result = $this->schulcloudAPIService->getNotifications(
			$this->schulcloudUrl, $this->accessToken, $this->refreshToken, $this->clientID, $this->clientSecret, $since
		);
		if (!isset($result['error'])) {
			$response = new DataResponse($result);
		} else {
			$response = new DataResponse($result, 401);
		}
		return $response;
	}

}
