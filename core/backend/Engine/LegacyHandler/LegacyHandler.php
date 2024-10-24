<?php
/**
 * SuiteCRM is a customer relationship management program developed by SalesAgility Ltd.
 * Copyright (C) 2021 SalesAgility Ltd.
 *
 * This program is free software; you can redistribute it and/or modify it under
 * the terms of the GNU Affero General Public License version 3 as published by the
 * Free Software Foundation with the addition of the following permission added
 * to Section 15 as permitted in Section 7(a): FOR ANY PART OF THE COVERED WORK
 * IN WHICH THE COPYRIGHT IS OWNED BY SALESAGILITY, SALESAGILITY DISCLAIMS THE
 * WARRANTY OF NON INFRINGEMENT OF THIRD PARTY RIGHTS.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT
 * ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS
 * FOR A PARTICULAR PURPOSE. See the GNU Affero General Public License for more
 * details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * In accordance with Section 7(b) of the GNU Affero General Public License
 * version 3, these Appropriate Legal Notices must retain the display of the
 * "Supercharged by SuiteCRM" logo. If the display of the logos is not reasonably
 * feasible for technical reasons, the Appropriate Legal Notices must display
 * the words "Supercharged by SuiteCRM".
 */

namespace App\Engine\LegacyHandler;

use Throwable;
use Psr\Log\LoggerInterface;
use App\Install\Service\InstallationUtilsTrait;
use BeanFactory;
use ControllerFactory;
use SugarApplication;
use SugarController;
use SugarThemeRegistry;
use Symfony\Component\HttpFoundation\RequestStack;
use User;

/**
 * Class LegacyHandler
 */
abstract class LegacyHandler
{
    use InstallationUtilsTrait;

    protected const MSG_LEGACY_BOOTSTRAP_FAILED     = 'Running legacy entry point failed';
    protected const MSG_SWITCH_SESSION_FAILED       = 'Running session switch failed';
    protected const MSG_RUN_LEGACY_BOOTSTRAP_FAILED = 'Running legacy Bootstrap failed';
    protected const MSG_SYSTEM_USER_FAILED          = 'Failed to load system user';

    /**
     * @var string
     *
     */
    protected string $projectDir = '';

    /**
     * @var string
     */
    protected string $legacyDir = '';

    /**
     * @var string
     */
    protected string $legacySessionName = '';

    /**
     * @var string
     */
    protected string $defaultSessionName = '';

    /**
     * @var LegacyScopeState
     */
    protected LegacyScopeState $state;

    /**
     * @var RequestStack
     */
    protected RequestStack $requestStack;

    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    /**
     * LegacyHandler constructor.
     *
     * @param string $projectDir
     * @param string $legacyDir
     * @param string $legacySessionName
     * @param string $defaultSessionName
     * @param LegacyScopeState $legacyScopeState
     * @param RequestStack $requestStack
     * @param LoggerInterface $logger
     */
    public function __construct(
        string           $projectDir,
        string           $legacyDir,
        string           $legacySessionName,
        string           $defaultSessionName,
        LegacyScopeState $legacyScopeState,
        RequestStack     $requestStack,
        LoggerInterface  $logger
    )
    {
        if ($projectDir === '' || !is_dir($projectDir)) {
            $this->projectDir = realpath(__DIR__ . '/../../../../');
        } else {
            $this->projectDir = $projectDir;
        }
        if ($legacyDir === '' || !is_dir($legacyDir)) {
            $this->legacyDir = realpath($this->projectDir . '/public/legacy');
        } else {
            $this->legacyDir = $legacyDir;
        }
        $this->legacySessionName = $legacySessionName;
        $this->defaultSessionName = $defaultSessionName;
        $this->state = $legacyScopeState;
        $this->requestStack = $requestStack;
    }

    /**
     * Legacy handler initialization method
     */
    public function init() : void
    {
        if (!empty($this->state->getActiveScope())) {
            return;
        }

        $this->startSession();

        $this->state->setActiveScope($this->getHandlerKey());
    }

    /**
     * Bootstraps legacy suite
     *
     * @return bool
     * @throws Throwable
     */
    public function runLegacyEntryPoint() : bool
    {
        try {
            if ($this->state->isLegacyBootstrapped()) {
                return true;
            }

            // Set up sugarEntry
            if (!defined('sugarEntry')) {
                define('sugarEntry', true);
            }

            if (!$this->isAppInstalled($this->legacyDir)) {
                global $installing;
                $installing = true;
            }

            // Load in legacy
            require_once $this->legacyDir . '/include/MVC/preDispatch.php';
            require_once $this->legacyDir . '/include/entryPoint.php';

            $this->state->setLegacyBootstrapped(true);

            return true;
        } catch (\Throwable $e) {
            $this->logger->error(
                self::MSG_RUN_LEGACY_BOOTSTRAP_FAILED,
                [
                    'exception' => $e->getMessage(),
                    'trace'     => $e->getTraceAsString(),
                    'method'    => $e->getFile(),
                    'line'      => $e->getLine()
                ]
            );
            throw $e;
        }
    }

    /**
     * @return string
     */
    public function getLegacyDir() : string
    {
        return $this->legacyDir;
    }

    /**
     * @return string
     */
    public function getProjectDir() : string
    {
        return $this->projectDir;
    }

    /**
     * Swap symfony session with legacy suite session
     *
     * @param string $sessionName
     * @param array $keysToSync
     *
     * @throws \Throwable
     */
    protected function switchSession(string $sessionName, array $keysToSync = []) : void
    {
        $carryOver = [];

        try {
            // Set working directory for legacy
            chdir($this->legacyDir);

            foreach ($keysToSync as $key) {
                if (!empty($_SESSION[$key])) {
                    $carryOver[$key] = $_SESSION[$key];
                }
            }

            session_write_close();
            session_name($sessionName);

            if (!isset($_COOKIE[$sessionName])) {
                $_COOKIE[$sessionName] = session_create_id();
            }

            session_id($_COOKIE[$sessionName]);
            session_start();

            foreach ($carryOver as $key => $value) {
                $_SESSION[$key] = $value;
            }
        } catch (\Throwable $e) {
            $this->logger->error(
                self::MSG_SWITCH_SESSION_FAILED,
                [
                    'exception' => $e->getMessage(),
                    'trace'     => $e->getTraceAsString(),
                    'method'    => $e->getFile(),
                    'line'      => $e->getLine()
                ]
            );
            throw $e;
        }
    }

    /**
     * Get handler key
     *
     * @return string
     */
    abstract public function getHandlerKey() : string;

    /**
     * Start Legacy Suite app
     *
     * @param string $currentModule
     *
     * @return void
     * @throws \Throwable
     * @see SugarApplication::execute
     *      Not calling:
     *      - insert_charset_header
     *      - setupPrint
     *      - checkHTTPReferer
     *      - controller->execute();
     *      - sugar_cleanup
     */
    protected function startLegacyApp(string $currentModule = '') : void
    {
        if ($this->state->isLegacyStarted()) {
            return;
        }

        try {
            require_once $this->legacyDir . '/include/MVC/SugarApplication.php';

            global $sugar_config;

            $app = new SugarApplication();

            $GLOBALS['app'] = $app;

            if (!empty($sugar_config['default_module'])) {
                $app->default_module = $sugar_config['default_module'];
            }

            $module = $app->default_module;
            if (!empty($currentModule)) {
                $module = $currentModule;
            }

            /** @var SugarController $controller */
            $controller = ControllerFactory::getController($module);
            $app->controller = $controller;
            // If the entry point is defined to not need auth, then don't authenticate.
            if (empty($_REQUEST['entryPoint']) ||
                $controller->checkEntryPointRequiresAuth($_REQUEST['entryPoint'])) {
                $app->loadUser();
                $app->ACLFilter();
                $app->preProcess();
                $controller->preProcess();
            }

            SugarThemeRegistry::buildRegistry();
            $app->loadLanguages();
            $app->loadDisplaySettings();
            $app->loadGlobals();
            $app->setupResourceManagement($module);

            $this->state->setLegacyStarted(true);
        } catch (\Throwable $e) {
            $this->logger->error(
                self::MSG_LEGACY_BOOTSTRAP_FAILED,
                [
                    'exception' => $e->getMessage(),
                    'trace'     => $e->getTraceAsString(),
                    'method'    => $e->getFile(),
                    'line'      => $e->getLine()
                ]
            );
            throw $e;
        }
    }

    /**
     * Load legacy system user
     */
    protected function loadSystemUser() : void
    {
        /** @var User $current_user */
        $currentUser = BeanFactory::newBean('Users');

        if (!empty($currentUser) && $currentUser instanceof User && $currentUser->getSystemUser() !== null) {
            $currentUser = $currentUser->getSystemUser();
            $GLOBALS['current_user'] = $currentUser;
        } else {
            $e = new \Exception();
            $this->logger->warning(
                self::MSG_SYSTEM_USER_FAILED,
                [
                    'exception' => $e->getMessage(),
                    'trace'     => $e->getTraceAsString(),
                    'method'    => $e->getFile(),
                    'line'      => $e->getLine()
                ]
            );
        }

    }

    /**
     * Close the legacy handler
     */
    public function close() : void
    {
        if ($this->state->getActiveScope() !== $this->getHandlerKey()) {
            return;
        }

        if (!empty($this->projectDir)) {
            chdir($this->projectDir);
        }

        $this->state->setActiveScope(null);
    }

    /**
     * @param string $module
     * @param string|null $record
     */
    protected function initController(string $module, string $record = null) : void
    {
        global $app;

        /** @var SugarController $controller */
        $controller = $app->controller;
        $controller->module = $module;

        if ($record) {
            $controller->record = $record;
        }
        $controller->loadBean();
    }

    /**
     * Disable legacy suite translations
     */
    protected function disableTranslations() : void
    {
        global $sugar_config, $app_strings;

        if (!isset($sugar_config)) {
            $sugar_config = [];
        }

        $sugar_config['disable_translations'] = true;

        $app_strings = disable_translations($app_strings);
    }

    /**
     * @return void
     */
    public function startSession() : void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        require_once $this->legacyDir . '/include/MVC/SugarApplication.php';

        $app = new SugarApplication();
        $app->startSession();
    }
}
