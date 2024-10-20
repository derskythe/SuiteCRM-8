<?php
/**
 * SugarCRM Community Edition is a customer relationship management program developed by
 * SugarCRM, Inc. Copyright (C) 2004-2013 SugarCRM Inc.
 *
 * SuiteCRM is an extension to SugarCRM Community Edition developed by SalesAgility Ltd.
 * Copyright (C) 2011 - 2018 SalesAgility Ltd.
 *
 * This program is free software; you can redistribute it and/or modify it under
 * the terms of the GNU Affero General Public License version 3 as published by the
 * Free Software Foundation with the addition of the following permission added
 * to Section 15 as permitted in Section 7(a): FOR ANY PART OF THE COVERED WORK
 * IN WHICH THE COPYRIGHT IS OWNED BY SUGARCRM, SUGARCRM DISCLAIMS THE WARRANTY
 * OF NON INFRINGEMENT OF THIRD PARTY RIGHTS.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT
 * ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS
 * FOR A PARTICULAR PURPOSE. See the GNU Affero General Public License for more
 * details.
 *
 * You should have received a copy of the GNU Affero General Public License along with
 * this program; if not, see http://www.gnu.org/licenses or write to the Free
 * Software Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA
 * 02110-1301 USA.
 *
 * You can contact SugarCRM, Inc. headquarters at 10050 North Wolfe Road,
 * SW2-130, Cupertino, CA 95014, USA. or at email address contact@sugarcrm.com.
 *
 * The interactive user interfaces in modified source and object code versions
 * of this program must display Appropriate Legal Notices, as required under
 * Section 5 of the GNU Affero General Public License version 3.
 *
 * In accordance with Section 7(b) of the GNU Affero General Public License version 3,
 * these Appropriate Legal Notices must retain the display of the "Powered by
 * SugarCRM" logo and "Supercharged by SuiteCRM" logo. If the display of the logos is not
 * reasonably feasible for technical reasons, the Appropriate Legal Notices must
 * display the words "Powered by SugarCRM" and "Supercharged by SuiteCRM".
 */
if (!defined('sugarEntry') || !sugarEntry) {
    exit('Not A Valid Entry Point');
}

/*
 * Created on Mar 21, 2007
 *
 * To change the template for this generated file go to
 * Window - Preferences - PHPeclipse - PHP - Code Templates
 */
require_once __DIR__ . '/../../include/MVC/Controller/ControllerFactory.php';
require_once __DIR__ . '/../../include/MVC/View/ViewFactory.php';

/**
 * SugarCRM application.
 *
 * @api
 */
class SugarApplication
{
    public $controller;
    public bool $headerDisplayed = false;
    public string $default_module = 'Home';
    public string $default_action = 'index';

    public function __construct()
    {
    }

    /**
     * Perform execution of the application. This method is called from index2.php.
     *
     * @throws Exception
     */
    public function execute() : void
    {
        global $sugar_config;
        if (!empty($sugar_config['default_module'])) {
            $this->default_module = $sugar_config['default_module'];
        }
        $module = $this->default_module;
        if (!empty($_REQUEST['module'])) {
            $module = $_REQUEST['module'];
        }
        insert_charset_header();
        $this->setupPrint();
        $this->controller = ControllerFactory::getController($module);
        // If the entry point is defined to not need auth, then don't authenticate.
        if (empty($_REQUEST['entryPoint'])
            || $this->controller->checkEntryPointRequiresAuth($_REQUEST['entryPoint'])) {
            $this->loadUser();
            $this->ACLFilter();
            $this->preProcess();
            $this->controller->preProcess();
            $this->checkHTTPReferer();
        }

        SugarThemeRegistry::buildRegistry();
        $this->loadLanguages();
        $this->loadDisplaySettings();
        $this->loadGlobals();
        $this->setupResourceManagement($module);
        $this->controller->execute();
        sugar_cleanup();
    }

    /**
     * Load the authenticated user. If there is not an authenticated user then redirect to login screen.
     */
    public function loadUser() : void
    {
        global $authController, $sugar_config;
        // Double check the server's unique key is in the session.  Make sure this is not an attempt to hijack a session
        $user_unique_key = $_SESSION['unique_key'] ?? '';
        $server_unique_key = $sugar_config['unique_key'] ?? '';
        $allowed_actions = (!empty($this->controller->allowed_actions))
            ? $this->controller->allowed_actions
            : [ 'Authenticate', 'Login', 'LoggedOut' ];

        $authController = new AuthenticationController();

        if (($user_unique_key !== $server_unique_key)
            && (!isset($_SESSION['login_error']))
            && (!in_array($this->controller->action, $allowed_actions, true))) {
            session_destroy();

            if (!empty($this->controller->action)) {
                if ('delete' === strtolower($this->controller->action)) {
                    $this->controller->action = 'DetailView';
                } elseif ('save' === strtolower($this->controller->action)) {
                    $this->controller->action = 'EditView';
                } elseif ('quickcreate' === strtolower($this->controller->action)) {
                    $this->controller->action = 'index';
                    $this->controller->module = 'home';
                } elseif (isset($_REQUEST['massupdate']) || isset($_GET['massupdate'])
                    || isset($_POST['massupdate'])) {
                    $this->controller->action = 'index';
                } elseif ($this->isModifyAction()) {
                    $this->controller->action = 'index';
                } elseif ($this->controller->action === $this->default_action
                    && $this->controller->module === $this->default_module) {
                    $this->controller->action = '';
                    $this->controller->module = '';
                } elseif ('alerts' === strtolower($this->controller->module) && 'get' === strtolower(
                        $this->controller->action
                    )) {
                    echo 'lost password';
                    if (preg_match('/\bentryPoint=Changenewpassword\b/', $_SERVER['HTTP_REFERER'])) {
                        echo ' - change new password';
                    }
                    exit;
                }
            }

            $authController->authController->redirectToLogin($this);
        }

        $GLOBALS['current_user'] = BeanFactory::newBean('Users');

        $isLogicActionCall =
            'Users' === $this->controller->module && in_array($this->controller->action, $allowed_actions);
        if (isset($_SESSION['authenticated_user_id'])) {
            // set in modules/Users/Authenticate.php
            if (!$authController->sessionAuthenticate()) {
                // if the object we get back is null for some reason, this will break - like user prefs are corrupted
                $GLOBALS['log']->fatal(
                    sprintf(
                        'User retrieval for ID: (%s) does not exist in database or retrieval failed catastrophically.
                    Calling session_destroy() and sending user to Login page.',
                        $_SESSION['authenticated_user_id']
                    )
                );
                session_destroy();
                self::redirect('index.php?action=Login&module=Users');
                exit;
            }// fi
        } elseif (!$isLogicActionCall || !empty($_REQUEST['entryPoint'])) {
            session_destroy();
            self::redirect('index.php?action=Login&module=Users');
            exit;
        }
        $GLOBALS['log']->debug('Current user is: ' . $GLOBALS['current_user']->user_name);

        // set cookies
        if (isset($_SESSION['authenticated_user_id'])) {
            $GLOBALS['log']->debug('setting cookie ck_login_id_20 to ' . $_SESSION['authenticated_user_id']);
            self::setCookie(
                'ck_login_id_20',
                $_SESSION['authenticated_user_id'],
                time() + 86400 * 90
            );
        }
        if (isset($_SESSION['authenticated_user_theme'])) {
            $GLOBALS['log']->debug('setting cookie ck_login_theme_20 to ' . $_SESSION['authenticated_user_theme']);
            self::setCookie(
                'ck_login_theme_20',
                $_SESSION['authenticated_user_theme'],
                time() + 86400 * 90
            );
        }
        if (isset($_SESSION['authenticated_user_theme_color'])) {
            $GLOBALS['log']->debug(
                'setting cookie ck_login_theme_color_20 to ' . $_SESSION['authenticated_user_theme_color']
            );
            self::setCookie(
                'ck_login_theme_color_20',
                $_SESSION['authenticated_user_theme_color'],
                time() + 86400 * 90
            );
        }
        if (isset($_SESSION['authenticated_user_theme_font'])) {
            $GLOBALS['log']->debug(
                'setting cookie ck_login_theme_font_20 to ' .
                $_SESSION['authenticated_user_theme_font']
            );
            self::setCookie(
                'ck_login_theme_font_20',
                $_SESSION['authenticated_user_theme_font'],
                time() + 86400 * 90
            );
        }
        if (isset($_SESSION['authenticated_user_language'])) {
            $GLOBALS['log']->debug(
                'setting cookie ck_login_language_20 to ' .
                $_SESSION['authenticated_user_language']
            );
            self::setCookie(
                'ck_login_language_20',
                $_SESSION['authenticated_user_language'],
                time() + 86400 * 90
            );
        }
        // check if user can access
    }

    public function ACLFilter() : void
    {
        ACLController::filterModuleList($GLOBALS['moduleList']);
    }

    /**
     * setupResourceManagement
     * This function initialize the ResourceManager and calls the setup method
     * on the ResourceManager instance.
     */
    public function setupResourceManagement($module) : void
    {
        require_once __DIR__ . '/../../include/resource/ResourceManager.php';
        $resourceManager = ResourceManager::getInstance();
        $resourceManager->setup($module);
    }

    public function setupPrint() : void
    {
        $GLOBALS['request_string'] = '';

        // merge _GET and _POST, but keep the results local
        // this handles the issues where values come in one way or the other
        // without affecting the main super globals
        $merged = array_merge($_GET, $_POST);
        foreach ($merged as $key => $val) {
            if (is_array($val)) {
                foreach ($val as $k => $v) {
                    // If an array, then skip the urlencoding. This should be handled with stringify instead.
                    if (is_array($v)) {
                        continue;
                    }

                    $GLOBALS['request_string'] .= urlencode($key) . '[' . $k . ']=' . urlencode($v) . '&';
                }
            } else {
                $GLOBALS['request_string'] .= urlencode($key) . '=' . urlencode($val) . '&';
            }
        }
        $GLOBALS['request_string'] .= 'print=true';
    }

    /**
     * @throws Exception
     */
    public function preProcess() : void
    {
        $config = new Administration();
        $config->retrieveSettings();
        if (!empty($_SESSION['authenticated_user_id'])) {
            if (isset($_SESSION['hasExpiredPassword']) && '1' === $_SESSION['hasExpiredPassword']) {
                if ('Save' !== $this->controller->action && 'Logout' !== $this->controller->action) {
                    $this->controller->module = 'Users';
                    $this->controller->action = 'ChangePassword';
                    $record = $GLOBALS['current_user']->id;
                } else {
                    $this->handleOfflineClient();
                }
            } else {
                $ut = $GLOBALS['current_user']->getPreference('ut');
                if (empty($ut)
                    && 'AdminWizard' !== $this->controller->action
                    && 'EmailUIAjax' !== $this->controller->action
                    && 'Wizard' !== $this->controller->action
                    && 'SaveAdminWizard' !== $this->controller->action
                    && 'SaveUserWizard' !== $this->controller->action
                    && 'SaveTimezone' !== $this->controller->action
                    && 'Logout' !== $this->controller->action) {
                    $this->controller->module = 'Users';
                    $this->controller->action = 'SetTimezone';
                    $record = $GLOBALS['current_user']->id;
                } else {
                    if ('AdminWizard' !== $this->controller->action
                        && 'EmailUIAjax' !== $this->controller->action
                        && 'Wizard' !== $this->controller->action
                        && 'SaveAdminWizard' !== $this->controller->action
                        && 'SaveUserWizard' !== $this->controller->action) {
                        $this->handleOfflineClient();
                    }
                }
            }
        }
        $this->handleAccessControl();
    }

    public function handleOfflineClient() : void
    {
        if (!isset($GLOBALS['sugar_config']['disc_client']) || !$GLOBALS['sugar_config']['disc_client']) {
            return;
        }
        if (isset($_REQUEST['action']) && 'SaveTimezone' !== $_REQUEST['action']) {
            if (!file_exists(__DIR__ . '/../../modules/Sync/file_config.php')) {
                if ('InitialSync' !== $_REQUEST['action'] && 'Logout' !== $_REQUEST['action']
                    && ('Popup' !== $_REQUEST['action'] && 'Sync' !== $_REQUEST['module'])) {
                    // echo $_REQUEST['action'];
                    // die();
                    $this->controller->module = 'Sync';
                    $this->controller->action = 'InitialSync';
                }
            } else {
                require_once __DIR__ . '/../../modules/Sync/file_config.php';
                if (isset($file_sync_info['is_first_sync']) && $file_sync_info['is_first_sync']
                    && 'InitialSync' !== $_REQUEST['action'] && 'Logout' !== $_REQUEST['action']
                    && ('Popup' !== $_REQUEST['action'] && 'Sync' !== $_REQUEST['module'])) {
                    $this->controller->module = 'Sync';
                    $this->controller->action = 'InitialSync';
                }
            }
        }
        global $moduleList, $sugar_config, $sync_modules;
        if (file_exists(__DIR__ . '/../../modules/Sync/SyncController.php')) {
            require_once __DIR__ . '/../../modules/Sync/SyncController.php';
        }
        $GLOBALS['current_user']->is_admin = '0'; // No admins for disc client
    }

    /**
     * Handles everything related to authorization.
     */
    public function handleAccessControl() : void
    {
        if ($GLOBALS['current_user']->isDeveloperForAnyModule()) {
            return;
        }
        if (!empty($_REQUEST['action']) && 'RetrieveEmail' === $_REQUEST['action']) {
            return;
        }

        $module = $this->controller->module ?? '';
        $action = strtolower($this->controller->action ?? '');

        if (!empty($_REQUEST['import_module'] ?? '') && 'import' === strtolower($module)) {
            $module = $_REQUEST['import_module'] ?? '';
            $action = 'import';
        }

        $adminOnlyList = $GLOBALS['adminOnlyList'] ?? [];
        $adminOnlyModuleActions = $adminOnlyList[$module] ?? [];
        $adminOnlyAction = $adminOnlyModuleActions[$action] ?? $adminOnlyModuleActions['all'] ?? false;
        $isAdminOnly = !empty($adminOnlyAction) && 'allow' !== $adminOnlyAction;

        if ($isAdminOnly && !is_admin($GLOBALS['current_user'])) {
            $this->controller->hasAccess = false;

            return;
        }

        // Bug 20916 - Special case for check ACL access rights for Subpanel QuickCreates
        if (isset($_POST['action']) && 'SubpanelCreates' === $_POST['action']) {
            $actual_module = $_POST['target_module'];
            if (!empty($GLOBALS['modListHeader']) && !in_array($actual_module, $GLOBALS['modListHeader'], true)) {
                $this->controller->hasAccess = false;
            }

            return;
        }

        if (!empty($GLOBALS['current_user']) && empty($GLOBALS['modListHeader'])) {
            $GLOBALS['modListHeader'] = query_module_access_list($GLOBALS['current_user']);
        }

        if (in_array($this->controller->module, $GLOBALS['modInvisList'], true)
            && ((in_array('Activities', $GLOBALS['moduleList'], true)
                    && in_array('Calendar', $GLOBALS['moduleList'], true))
                && in_array($this->controller->module, $GLOBALS['modInvisListActivities'], true))
        ) {
            $this->controller->hasAccess = false;
        }
    }

    /**
     * Load only bare minimum of language that can be done before user init and MVC stuff.
     */
    public static function preLoadLanguages() : void
    {
        $GLOBALS['current_language'] = get_current_language();
        $GLOBALS['log']->debug('current_language is: ' . $GLOBALS['current_language']);
        // set module and application string arrays based upon selected language
        $GLOBALS['app_strings'] = return_application_language($GLOBALS['current_language']);
    }

    /**
     * Load application wide languages as well as module based languages so they are accessible
     * from the module.
     */
    public function loadLanguages() : void
    {
        $GLOBALS['current_language'] = get_current_language();
        $GLOBALS['log']->debug('current_language is: ' . $GLOBALS['current_language']);
        // set module and application string arrays based upon selected language
        $GLOBALS['app_strings'] = return_application_language($GLOBALS['current_language']);
        if (empty($GLOBALS['current_user']->id)) {
            $GLOBALS['app_strings']['NTC_WELCOME'] = '';
        }
        if (!empty($GLOBALS['system_config']->settings['system_name'])) {
            $GLOBALS['app_strings']['LBL_BROWSER_TITLE'] = $GLOBALS['system_config']->settings['system_name'];
        }
        $GLOBALS['app_list_strings'] = return_app_list_strings_language($GLOBALS['current_language']);
        $GLOBALS['mod_strings'] = return_module_language($GLOBALS['current_language'], $this->controller->module);
    }

    /**
     * checkDatabaseVersion
     * Check the db version sugar_version.php and compare to what the version is stored in the config table.
     * Ensure that both are the same.
     *
     * @throws Exception
     */
    public function checkDatabaseVersion(bool $dieOnFailure = true) : bool
    {
        $row_count = sugar_cache_retrieve('checkDatabaseVersion_row_count');
        $sugarDbVersion = $GLOBALS['sugar_db_version'];
        $db = DBManagerFactory::getInstance();
        if (SuiteCRM\database\DatabasePDOManager::isInit()) {
            $pdo = SuiteCRM\database\DatabasePDOManager::getInstance();

            if (null === $row_count || 0 === count($row_count)) {
                $params = [ 1 ];
                $params[':version'] = $sugarDbVersion;
                $result = $pdo->executeQueryResult(
                    'SELECT COUNT(*) AS the_count
                        FROM config
                        WHERE category=\'info\' AND name=\'sugar_version\' AND value = :version',
                    $params
                );
                $row = $pdo->fetchAssoc($result);
                $row_count = $row['the_count'];
                sugar_cache_put('checkDatabaseVersion_row_count', $row_count);
            }
        } else {
            $GLOBALS['log']->errror('PDO is not initialized!');
            if ($row_count === null) {
                $version_query =
                    sprintf(
                        'SELECT COUNT(*) AS the_count FROM config WHERE category=\'info\' AND name=\'sugar_version\' AND %s = %s',
                        $db->convert('value', 'text2char'),
                        $db->quoted($sugarDbVersion)
                    );

                $result = $db->query($version_query);
                $row = $db->fetchByAssoc($result);
                $row_count = $row['the_count'];
                sugar_cache_put('checkDatabaseVersion_row_count', $row_count);
            }
        }

        if ($row_count === 0 && empty($GLOBALS['sugar_config']['disc_client'])) {
            if ($dieOnFailure) {
                $replacementStrings = [
                    0 => $GLOBALS['sugar_version'],
                    1 => $GLOBALS['sugar_db_version'],
                ];
                sugar_die(string_format($GLOBALS['app_strings']['ERR_DB_VERSION'], $replacementStrings));
            } else {
                return false;
            }
        }

        return true;
    }

    /**
     * Load the themes/images.
     */
    public function loadDisplaySettings() : void
    {
        global $theme;

        // load the user's default theme
        $theme = $GLOBALS['current_user']->getPreference('user_theme');

        if (is_null($theme)) {
            $theme = $GLOBALS['sugar_config']['default_theme'];
            if (!empty($_SESSION['authenticated_user_theme'])) {
                $theme = $_SESSION['authenticated_user_theme'];
            } else {
                if (!empty($_COOKIE['sugar_user_theme'])) {
                    $theme = $_COOKIE['sugar_user_theme'];
                }
            }

            if (isset($_SESSION['authenticated_user_theme']) && '' !== $_SESSION['authenticated_user_theme']) {
                $_SESSION['theme_changed'] = false;
            }
        }

        $available_themes = SugarThemeRegistry::availableThemes();
        if (!isset($available_themes[$theme])) {
            $theme = $GLOBALS['sugar_config']['default_theme'];
        }

        if (!is_null($theme) && !headers_sent()) {
            self::setCookie(
                'sugar_user_theme',
                $theme,
                time() + 31536000,
                null,
                null,
                isSSL(),
                true
            ); // expires in a year
        }

        SugarThemeRegistry::set($theme);
        require_once __DIR__ . '/../../include/utils/layout_utils.php';
        $GLOBALS['image_path'] = SugarThemeRegistry::current()->getImagePath() . '/';
        if (defined('TEMPLATE_URL')) {
            $GLOBALS['image_path'] = TEMPLATE_URL . '/' . $GLOBALS['image_path'];
        }

        if (isset($GLOBALS['current_user'])) {
            $GLOBALS['gridline'] = (int) ('on' === $GLOBALS['current_user']->getPreference('gridline'));
            $GLOBALS['current_user']->setPreference('user_theme', $theme, 0, 'global');
        }
    }

    public function loadLicense() : void
    {
        loadLicense();
        global $user_unique_key, $server_unique_key;
        $user_unique_key = $_SESSION['unique_key'] ?? '';
        $server_unique_key = $sugar_config['unique_key'] ?? '';
    }

    public function loadGlobals() : void
    {
        global $currentModule;
        $currentModule = $this->controller->module;
        if ($this->controller->module === $this->default_module) {
            $_REQUEST['module'] = $this->controller->module;
            if (empty($_REQUEST['action'])) {
                $_REQUEST['action'] = $this->default_action;
            }
        }
    }

    /**
     * Actions that modify data in this controller's instance and thus require referrers.
     *
     * @var array
     */
    protected array $modifyActions = [];

    /**
     * Actions that always modify data and thus require referrers
     * save* and delete* hardcoded as modified.
     *
     * @var array
     */
    private array $globalModifyActions = [
        'massupdate',
        'configuredashlet',
        'import',
        'importvcardsave',
        'inlinefieldsave',
        'wlsave',
        'quicksave',
    ];

    /**
     * Modules that modify data and thus require referrers for all actions.
     */
    private array $modifyModules = [
        'Administration' => true,
        'UpgradeWizard'  => true,
        'Configurator'   => true,
        'Studio'         => true,
        'ModuleBuilder'  => true,
        'Emails'         => true,
        'DCETemplates'   => true,
        'DCEInstances'   => true,
        'DCEActions'     => true,
        'Trackers'       => [ 'trackersettings' ],
        'SugarFavorites' => [ 'tag' ],
        'Import'         => [ 'last', 'undo' ],
        'Users'          => [ 'changepassword', 'generatepassword' ],
    ];

    protected function isModifyAction() : bool
    {
        $action = strtolower($this->controller->action);
        if (str_starts_with($action, 'save') || str_starts_with($action, 'delete')) {
            return true;
        }
        if (isset($this->modifyModules[$this->controller->module])) {
            if (true === $this->modifyModules[$this->controller->module]) {
                return true;
            }
            if (in_array($this->controller->action, $this->modifyModules[$this->controller->module], true)) {
                return true;
            }
        }
        if (in_array($this->controller->action, $this->globalModifyActions, true)) {
            return true;
        }
        if (in_array($this->controller->action, $this->modifyActions, true)) {
            return true;
        }

        return false;
    }

    /**
     * The list of the actions excepted from referer checks by default.
     *
     * @var array
     */
    protected array $whiteListActions = [ 'index',
                                          'ListView',
                                          'DetailView',
                                          'EditView',
                                          'oauth',
                                          'authorize',
                                          'Authenticate',
                                          'Login',
                                          'SupportPortal' ];

    /**
     * Checks a request to ensure the request is coming from a valid source or it is for one of the white listed
     * actions.
     *
     * @throws SmartyException
     * @throws SmartyException
     */
    protected function checkHTTPReferer(bool $dieIfInvalid = true) : bool
    {
        global $sugar_config;
        if (!empty($sugar_config['http_referer']['actions'])) {
            $this->whiteListActions = array_merge($sugar_config['http_referer']['actions'], $this->whiteListActions);
        }

        $strong = empty($sugar_config['http_referer']['weak']);

        // Bug 39691 - Make sure localhost and 127.0.0.1 are always valid HTTP referers
        $whiteListReferers = [ '127.0.0.1', 'localhost' ];
        if (!empty($_SERVER['SERVER_ADDR'])) {
            $whiteListReferers[] = $_SERVER['SERVER_ADDR'];
        }
        if (!empty($sugar_config['http_referer']['list'])) {
            $whiteListReferers = array_merge($whiteListReferers, $sugar_config['http_referer']['list']);
        }

        if ($strong && empty($_SERVER['HTTP_REFERER']) && !in_array(
                $this->controller->action,
                $this->whiteListActions,
                true
            ) && $this->isModifyAction()) {
            $http_host = explode(':', $_SERVER['HTTP_HOST']);
            $whiteListActions = $this->whiteListActions;
            $whiteListActions[] = $this->controller->action;
            $whiteListString = '\'' . implode('\', \'', $whiteListActions) . '\'';
            if ($dieIfInvalid) {
                header('Cache-Control: no-cache, must-revalidate');
                $ss = new Sugar_Smarty();
                $ss->assign('host', $http_host[0]);
                $ss->assign('action', $this->controller->action);
                $ss->assign('whiteListString', $whiteListString);
                $ss->display('include/MVC/View/tpls/xsrf.tpl');
                sugar_cleanup(true);
            }

            return false;
        }

        if (!empty($_SERVER['HTTP_REFERER']) && !empty($_SERVER['SERVER_NAME'])) {
            $http_ref = parse_url($_SERVER['HTTP_REFERER']);
            if ($http_ref['host'] !== $_SERVER['SERVER_NAME'] && !in_array(
                    $this->controller->action,
                    $this->whiteListActions,
                    true
                )
                && (empty($whiteListReferers) || !in_array($http_ref['host'], $whiteListReferers, true))) {
                if ($dieIfInvalid) {
                    header('Cache-Control: no-cache, must-revalidate');
                    $whiteListActions = $this->whiteListActions;
                    $whiteListActions[] = $this->controller->action;
                    $whiteListString = "'" . implode("', '", $whiteListActions) . "'";

                    $ss = new Sugar_Smarty();
                    $ss->assign('host', $http_ref['host']);
                    $ss->assign('action', $this->controller->action);
                    $ss->assign('whiteListString', $whiteListString);
                    $ss->display('include/MVC/View/tpls/xsrf.tpl');
                    sugar_cleanup(true);
                }

                return false;
            }
        }

        return true;
    }

    public function startSession() : void
    {
        require_once __DIR__ . '/../utils.php';

        $sessionIdCookie = $_COOKIE[session_name()] ?? null;
        if (isset($_REQUEST['MSID'])) {
            session_id($_REQUEST['MSID']);
            session_start();
            if (!isset($_SESSION['user_id'])) {
                if (isset($_COOKIE[session_name()])) {
                    self::setCookie(session_name(), '', time() - 42000, '/');
                }
                sugar_cleanup(false);
                session_destroy();
                exit('Not a valid entry method');
            }
        } else {
            if (can_start_session()) {
                session_start();
            }
        }

        // set the default module to either Home or specified default
        $default_module = !empty($GLOBALS['sugar_config']['default_module'])
            ? $GLOBALS['sugar_config']['default_module']
            : 'Home';

        // set session expired message if login module and action are set to a non login default
        // AND session id in cookie is set but super global session array is empty
        if (isset($_REQUEST['login_module'], $_REQUEST['login_action'])
            && !(($_REQUEST['login_module'] === $default_module) && ('index' === $_REQUEST['login_action']))
            && !is_null($sessionIdCookie) && empty($_SESSION)) {
            self::setCookie('loginErrorMessage', 'LBL_SESSION_EXPIRED', time() + 30, '/');
        }

        LogicHook::initialize()->call_custom_logic('', 'after_session_start');
    }

    public function endSession() : void
    {
        session_destroy();
    }

    /**
     * Redirect to another URL.
     *
     * @param string $url The URL to redirect to
     */
    public static function redirect(string $url) : void
    {
        /*
         * If the headers have been sent, then we cannot send an additional location header
         * so we will output a javascript redirect statement.
         */

        if (!empty($_REQUEST['ajax_load'])) {
            ob_get_clean();
            $ajax_ret = [
                'content' => sprintf('<script>SUGAR.ajaxUI.loadContent(\'%s\');</script> ', $url),
                'menu'    => [
                    'module' => $_REQUEST['module'],
                    'label' => translate($_REQUEST['module']),
                ],
            ];
            $json = getJSONobj();
            echo $json->encode($ajax_ret);
        } else {
            if (headers_sent()) {
                echo sprintf('<script>SUGAR.ajaxUI.loadContent(\'%s\');</script>', $url);
            } else {
                // @ob_end_clean(); // clear output buffer
                session_write_close();
                header('HTTP/1.1 301 Moved Permanently');
                header('Location: ' . $url);
            }
        }
        if (!defined('SUITE_PHPUNIT_RUNNER')) {
            exit;
        }
    }

    /**
     * classic redirect to another URL, but check first that URL start with "Location:"...
     */
    public static function headerRedirect(string $header_URL) : void
    {
        if (preg_match('/\s*Location:\s*(.*)$/', $header_URL, $matches)) {
            $href = $matches[1];
            self::redirect($href);
        } else {
            header($header_URL);
        }
    }

    /**
     * Storing messages into session.
     *
     * @param string $message
     *
     * @throws Exception
     */
    public static function appendErrorMessage(string $message) : void
    {
        self::appendMessage('user_error_message', $message);
    }

    /**
     * picking up the messages from the session and clearing session storage array.
     *
     * @return array messages
     * @throws Exception
     */
    public static function getErrorMessages() : array
    {
        return self::getMessages('user_error_message');
    }

    /**
     * Storing messages into session.
     *
     * @param string $message
     *
     * @throws Exception
     */
    public static function appendSuccessMessage(string $message) : void
    {
        self::appendMessage('user_success_message', $message);
    }

    /**
     * picking up the messages from the session and clearing session storage array.
     *
     * @return array messages
     * @throws Exception
     */
    public static function getSuccessMessages() : array
    {
        return self::getMessages('user_success_message');
    }

    /**
     * Storing messages into session.
     *
     * @param string $message
     *
     * @throws Exception
     */
    protected static function appendMessage(string $type, string $message) : void
    {
        self::validateMessageType($type);

        if (empty($_SESSION[$type]) || !is_array($_SESSION[$type])) {
            $_SESSION[$type] = [];
        }
        if (!in_array($message, $_SESSION[$type], true)) {
            $_SESSION[$type][] = $message;
        }
    }

    /**
     * picking up the messages from the session and clearing session storage array.
     *
     * @return array messages
     * @throws Exception
     */
    protected static function getMessages(string $type) : array
    {
        self::validateMessageType($type);

        if (isset($_SESSION[$type]) && is_array($_SESSION[$type])) {
            $messages = $_SESSION[$type];
            unset($_SESSION[$type]);

            return $messages;
        }

        return [];
    }

    /**
     * @param string $type possible message types: ['user_error_message', 'user_success_message']
     *
     * @throws Exception message type should be valid
     */
    protected static function validateMessageType(string $type) : void
    {
        if (!in_array($type, [ 'user_error_message', 'user_success_message' ])) {
            throw new \RuntimeException('Incorrect application message type: ' . $type);
        }
    }

    /**
     * Wrapper for the PHP setcookie() function, to handle cases where headers have
     * already been sent.
     *
     * @param string $name
     * @param string $value
     * @param int $expire
     * @param string|null $path
     * @param string|null $domain
     * @param bool $secure
     * @param bool $httponly
     */
    public static function setCookie(
        string      $name,
        string      $value,
        int         $expire = 0,
        string|null $path = null,
        string|null $domain = null,
        bool        $secure = false,
        bool        $httponly = true
    ) : void
    {
        if (isSSL()) {
            $secure = true;
        }
        if (null === $domain) {
            if (isset($_SERVER['HTTP_HOST'])) {
                $domain =
                    preg_replace(
                        '/(:\d+)$/',
                        '',
                        $_SERVER['HTTP_HOST']
                    ); // Fix #9898 Invalid cookie domain when using non-standard HTTP Port.
            } else {
                $domain = 'localhost';
            }
        }

        $defaultCookiePath = ini_get('session.cookie_path');
        if (null === $path) {
            if (empty($defaultCookiePath)) {
                $path = '/';
            } else {
                $path = $defaultCookiePath;
            }
        }

        if (!headers_sent()) {
            setcookie($name, $value, $expire, $path, $domain, $secure, $httponly);
        }

        $_COOKIE[$name] = $value;
    }

    protected array $redirectVars = [ 'module', 'action', 'record', 'token', 'oauth_token', 'mobile' ];

    /**
     * Create string to attach to login URL with vars to preserve post-login.
     *
     * @return string URL part with login vars
     */
    public function createLoginVars() : string
    {
        $ret = [];
        foreach ($this->redirectVars as $var) {
            if (!empty($this->controller->$var)) {
                $ret['login_' . $var] = $this->controller->$var;
                continue;
            }
            if (!empty($_REQUEST[$var])) {
                $ret['login_' . $var] = $_REQUEST[$var];
            }
        }
        if (isset($_REQUEST['mobile'])) {
            $ret['mobile'] = $_REQUEST['mobile'];
        }
        if (isset($_REQUEST['no_saml'])) {
            $ret['no_saml'] = $_REQUEST['no_saml'];
        }
        if (empty($ret)) {
            return '';
        }

        return '&' . http_build_query($ret);
    }

    /**
     * Get the list of vars passed with login form.
     *
     * @param bool $add_empty Add empty vars to the result?
     *
     * @return array List of vars passed with login
     */
    public function getLoginVars(bool $add_empty = true) : array
    {
        $ret = [];
        foreach ($this->redirectVars as $var) {
            if (!empty($_REQUEST['login_' . $var]) || $add_empty) {
                $ret['login_' . $var] = $_REQUEST['login_' . $var] ?? '';
            }
        }

        return $ret;
    }

    /**
     * Get URL to redirect after the login.
     *
     * @return string the URL to redirect to
     */
    public function getLoginRedirect() : string
    {
        $vars = [];
        foreach ($this->redirectVars as $var) {
            if (!empty($_REQUEST['login_' . $var])) {
                $vars[$var] = $_REQUEST['login_' . $var];
            }
        }

        if (isset($_REQUEST['mobile'])) {
            $vars['mobile'] = $_REQUEST['mobile'];
        }
        if (empty($vars)) {
            return 'index.php?module=Home&action=index';
        }

        return 'index.php?' . http_build_query($vars);
    }
}
