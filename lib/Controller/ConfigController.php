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
     */
    public function setConfig($values) {
        foreach ($values as $key => $value) {
            $this->config->setUserValue($this->userId, Application::APP_ID, $key, $value);
        }
        $response = new DataResponse(1);
        return $response;
    }

    /**
     * set admin config values
     */
    public function setAdminConfig($values) {
        foreach ($values as $key => $value) {
            $this->config->setAppValue(Application::APP_ID, $key, $value);
        }
        $response = new DataResponse(1);
        return $response;
    }

    /**
     * receive oauth encrypted payload with protocol handler redirect
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function oauthProtocolRedirect($url) {
        $parts = parse_url($url);
        parse_str($parts['query'], $params);
        return $this->oauthRedirect($params['payload']);
    }

    /**
     * receive oauth encrypted payload
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function oauthRedirect($payload) {
        $configNonce = $this->config->getUserValue($this->userId, Application::APP_ID, 'nonce', '');
        //$clientID = $this->config->getAppValue(Application::APP_ID, 'client_id', '');
        //$clientSecret = $this->config->getAppValue(Application::APP_ID, 'client_secret', '');
        $clientID = DEFAULT_CLIENT_ID;
        $clientSecret = DEFAULT_CLIENT_SECRET;
        $payloadArray = json_decode($payload, true);

        // anyway, reset nonce
        $this->config->setUserValue($this->userId, Application::APP_ID, 'nonce', '');

        // TODO get all params given by redirection URL
        if (is_array($payloadArray) and $configNonce !== '' and $configNonce === $payloadArray['nonce']) {
            $result = $this->schulcloudAPIService->requestOAuthAccessToken([
                'client_id' => $clientID,
                'client_secret' => $clientSecret,
                'code' => $code,
                'state' => $state
            ], 'POST');
            if (isset($result['access_token'])) {
                $accessToken = $result['access_token'];
                $this->config->setUserValue($this->userId, Application::APP_ID, 'token', $accessToken);
                return new RedirectResponse(
                    $this->urlGenerator->linkToRoute('settings.PersonalSettings.index', ['section' => 'linked-accounts']) .
                    '?schulcloudToken=success'
                );
            }
            $result = $this->l->t('Error getting OAuth access token');
        }

        return new RedirectResponse(
            $this->urlGenerator->linkToRoute('settings.PersonalSettings.index', ['section' => 'linked-accounts']) .
            '?schulcloudToken=error&message=' . urlencode($result)
        );
    }

}
