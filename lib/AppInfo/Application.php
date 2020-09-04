<?php
/**
 * Nextcloud - Schulcloud
 *
 *
 * @author Julien Veyssier <eneiluj@posteo.net>
 * @copyright Julien Veyssier 2020
 */

namespace OCA\Schulcloud\AppInfo;

use OCP\IContainer;

use OCP\AppFramework\App;
use OCP\AppFramework\IAppContainer;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;

use OCA\Schulcloud\Controller\PageController;
use OCA\Schulcloud\Dashboard\SchulcloudWidget;

/**
 * Class Application
 *
 * @package OCA\Schulcloud\AppInfo
 */
class Application extends App implements IBootstrap {

    public const APP_ID = 'integration_schulcloud';

    /**
     * Constructor
     *
     * @param array $urlParams
     */
    public function __construct(array $urlParams = []) {
        parent::__construct(self::APP_ID, $urlParams);

        $container = $this->getContainer();
    }

    public function register(IRegistrationContext $context): void {
        $context->registerDashboardWidget(SchulcloudWidget::class);
    }

    public function boot(IBootContext $context): void {
    }
}

