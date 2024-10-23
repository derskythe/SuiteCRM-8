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

namespace App\Install\LegacyHandler;

use App\Engine\LegacyHandler\LegacyHandler;
use App\Engine\LegacyHandler\LegacyScopeState;
use App\Engine\Model\Feedback;
use App\Install\Service\Installation\InstallStatus;
use App\Install\Service\InstallationUtilsTrait;
use App\Install\Service\InstallPreChecks;
use App\Security\AppSecretGenerator;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use PDO;
use PDOException;
use Psr\Log\LoggerInterface;
use Symfony\Component\Filesystem\Exception\IOExceptionInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Class InstallHandler
 *
 * @package App\Legacy
 */
class InstallHandler extends LegacyHandler
{
    use InstallationUtilsTrait;

    public const HANDLER_KEY = 'install';

    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    protected AppSecretGenerator $appSecretGenerator;

    /**
     * @inheritDoc
     */
    public function getHandlerKey() : string
    {
        return self::HANDLER_KEY;
    }

    /**
     * InstallHandler constructor.
     *
     * @param string $projectDir
     * @param string $legacyDir
     * @param string $legacySessionName
     * @param string $defaultSessionName
     * @param LegacyScopeState $legacyScopeState
     * @param RequestStack $requestStack
     * @param LoggerInterface $logger
     * @param AppSecretGenerator $appSecretGenerator
     */
    public function __construct(
        string             $projectDir,
        string             $legacyDir,
        string             $legacySessionName,
        string             $defaultSessionName,
        LegacyScopeState   $legacyScopeState,
        RequestStack       $requestStack,
        LoggerInterface    $logger,
        AppSecretGenerator $appSecretGenerator
    )
    {
        parent::__construct(
            $projectDir,
            $legacyDir,
            $legacySessionName,
            $defaultSessionName,
            $legacyScopeState,
            $requestStack,
            $logger
        );
        $this->logger = $logger;
        $this->appSecretGenerator = $appSecretGenerator;
    }

    /**
     * Init legacy
     *
     * @throws \Throwable
     */
    public function initLegacy() : void
    {
        $this->switchSession($this->legacySessionName);
        chdir($this->legacyDir);

        global $installing;
        $installing = true;
    }

    /**
     * Close legacy
     *
     * @throws \Throwable
     */
    public function closeLegacy() : void
    {
        $this->switchSession($this->defaultSessionName);
        chdir($this->projectDir);
    }

    /**
     * @return Feedback
     * @throws \Throwable
     */
    public function installLegacy() : \Feedback
    {
        $this->switchSession($this->legacySessionName);
        chdir($this->legacyDir);

        $errorLevelStored = error_reporting();
        error_reporting(0);

        $feedback = new Feedback();

        if (!is_file('config_si.php')) {
            $this->logger->error('config_si.php is required for CLI Install.');

            return $feedback->setSuccess(false)->setMessages([ 'config_si.php is required for CLI Install.' ]);
        }

        $_REQUEST['goto'] = 'SilentInstall';
        $_REQUEST['cli'] = 'true';

        ob_start();
        ob_start();
        /* @noinspection PhpIncludeInspection */
        include_once 'install.php';
        ob_end_clean();
        ob_end_clean();

        if (is_file('config.php')) {
            $feedback->setSuccess(true)->setMessages([ 'SuiteCRM Installation Completed' ]);
        } else {
            $feedback->setSuccess(false)->setMessages([ 'SuiteCRM Installation Failed' ]);
        }

        chdir($this->projectDir);
        $this->switchSession($this->defaultSessionName);

        error_reporting($errorLevelStored);

        return $feedback;
    }

    /**
     * @param array $context
     *
     * @return Feedback
     * @throws \Throwable
     */
    public function runSystemCheck(array $context) : \Feedback
    {
        $this->switchSession($this->legacySessionName);
        chdir($this->legacyDir);

        $errorLevelStored = error_reporting();
        error_reporting(0);

        $this->runLegacyEntryPoint();

        /* @noinspection PhpIncludeInspection */
        require_once $this->legacyDir . '/include/portability/InstallValidation/InstallValidation.php';

        $validator = (new \InstallValidation())->validate($context);
        $result = $validator->result();

        $feedback = new Feedback();
        $feedback->setSuccess(true);

        $hasErrors = $result['hasValidationError'] ?? false;

        if ($hasErrors === true) {
            $feedback->setSuccess(false);
            $feedback->setStatusCode(InstallStatus::VALIDATION_FAILED);
            $feedback->setErrors($result);
        }

        file_put_contents('.installed_checked', 'true');

        chdir($this->projectDir);
        $this->switchSession($this->defaultSessionName);

        error_reporting($errorLevelStored);

        return $feedback;
    }

    /**
     * @param array $inputArray
     *
     * @return Feedback
     * @throws \JsonException
     */
    public function runCheckRouteAccess(array $inputArray) : \FeedBack
    {
        $results = [];
        $url = $inputArray['site_host'];

        $log = new Logger('install.log');
        $log->pushHandler(new StreamHandler($this->projectDir . '/logs/install.log', Logger::DEBUG));

        $feedback = new Feedback();
        $feedback->setSuccess(true);

        require_once($this->projectDir . 'core/backend/Install/Service/InstallPreChecks.php');
        $installChecks = new InstallPreChecks(
            $log,
            $this->projectDir,
            $this->legacyDir
        );

        $results[] = $installChecks->checkMainPage($url);
        $results[] = $installChecks->checkGraphQlAPI($url);

        $warnings = [];
        $errorsFound = false;

        foreach ($results as $result) {
            if (is_array($result['errors'])) {
                foreach ($result['errors'] as $error) {

                    $errorsFound = true;

                    if (empty($error)) {
                        continue;
                    }

                    if (in_array(
                            $error,
                            $installChecks->modStrings,
                            true
                        ) && $error !== $installChecks->modStrings['LBL_EMPTY']) {
                        $warnings[] = 'One or More Failed Checks: ' . $error .
                            ' Please refer to the logs/install.log';
                    }

                }
                continue;
            }

            if (!empty($result['errors'])) {
                $warnings[] = $result['errors'];
            }
        }

        if ($errorsFound) {
            $warnings[] = 'One or More Failed Checks: Please refer to the logs/install.log';
        }

        if (isset($warnings)) {
            $feedback->setWarnings($warnings);
        }

        return $feedback;
    }

    /**
     * @param array $inputArray
     *
     * @return bool
     */
    public function createConfig(array $inputArray) : bool
    {
        $siteURL = rtrim($inputArray['site_host'] ?? '', " \t\n\r\0\x0B/");
        $configArray = [
            'dbUSRData'                           => 'same',
            'default_currency_iso4217'            => 'USD',
            'default_currency_name'               => 'US Dollar',
            'default_currency_significant_digits' => '2',
            'default_currency_symbol'             => '$',
            'default_date_format'                 => 'Y-m-d',
            'default_decimal_seperator'           => '.',
            'default_export_charset'              => 'ISO-8859-1',
            'default_language'                    => 'en_us',
            'default_locale_name_format'          => 's f l',
            'default_number_grouping_seperator'   => ',',
            'default_time_format'                 => 'H:i',
            'export_delimiter'                    => ',',
            'setup_db_admin_password'             => $inputArray['db_password'],
            'setup_db_admin_user_name'            => $inputArray['db_username'],
            'setup_db_port_num'                   => $inputArray['db_port'],
            'setup_db_create_database'            => 1,
            'setup_db_database_name'              => $inputArray['db_name'],
            'setup_db_drop_tables'                => 0,
            'setup_db_host_name'                  => $inputArray['db_host'],
            'demoData'                            => $inputArray['demoData'],
            'setup_db_type'                       => 'mysql',
            'setup_db_username_is_privileged'     => true,
            'setup_site_admin_password'           => $inputArray['site_password'],
            'setup_site_admin_user_name'          => $inputArray['site_username'],
            'setup_site_url'                      => $siteURL,
            'setup_system_name'                   => 'SuiteCRM',
        ];

        $contents = '<?php' . PHP_EOL . '$sugar_config_si = ' . var_export($configArray, 1) . ';' . PHP_EOL;
        $filesystem = new Filesystem();

        try {
            chdir($this->legacyDir);
            $filesystem->dumpFile('config_si.php', $contents);
            chdir($this->projectDir);

            return true;
        } catch (IOExceptionInterface $exception) {
            $this->logger->error(
                'An error occurred while creating your silent install config at ' .
                $exception->getPath()
            );

            return false;
        }
    }

    /**
     * Check db host connection before proceeding
     *
     * @param array $inputArray
     *
     * @return bool
     */
    public function checkDBConnection(array $inputArray) : bool
    {
        $dbHost = $inputArray['db_host'];
        $dbPort = $inputArray['db_port'];
        $hostString = !empty($dbPort) ? $dbHost . ':' . $dbPort : $dbHost;

        try {
            new PDO(
                'mysql:host=' . $hostString . ';',
                $inputArray['db_username'],
                $inputArray['db_password']
            );
        } catch (PDOException $e) {
            $message = sprintf(
                'An error occurred while checking the Database Host Connection %s File: %s Line: %d. Hostname: %s, Username: %s, Password: %s',
                $e->getMessage(),
                $e->getFile(),
                $e->getLine(),
                $hostString,
                $inputArray['db_username'],
                $inputArray['db_password']
            );
            $this->logger->critical($message);
            $this->logger->critical($e->getMessage(), $e->getTrace());

            return false;
        }

        return true;
    }

    /**
     * Create local env file
     *
     * @param array $inputArray
     *
     * @return bool
     */
    public function createEnv(array $inputArray) : bool
    {
        $password = urlencode($inputArray['db_password'] ?? '');
        $username = urlencode($inputArray['db_username'] ?? '');
        $dbName = $inputArray['db_name'] ?? '';
        $host = $inputArray['db_host'] ?? '';
        $port = $inputArray['db_port'] ?? '';
        $hostString = !empty($port) ? $host . ':' . $port : $host;

        $content = sprintf(
            'DATABASE_URL="mysql://%s:%s@%s/%s"',
            $username,
            $password,
            $hostString,
            $dbName
        );
        $content .= 'APP_SECRET=' . $this->appSecretGenerator->generate();
        $this->logger->info('Generated randomly generated APP_SECRET for .env.local');

        $filesystem = new Filesystem();
        try {
            chdir($this->projectDir);

            $filesystem->dumpFile('.env.local', $content);

            chdir($this->legacyDir);

            return true;
        } catch (IOExceptionInterface $exception) {
            $this->logger->error(
                'An error occurred while creating the Database Env config at ' . $exception->getPath()
            );

            return false;
        }
    }

    /**
     * Check if is installed
     *
     * @return bool is installed
     */
    public function isInstalled() : bool
    {
        $filesystem = new Filesystem();

        $this->logger->error('A SuiteCRM Instance is already been installed. Stopping ');

        return $filesystem->exists('.env.local');
    }

    /**
     * Check if is legacy app is installed
     *
     * @return bool is installed
     */
    public function isLegacyInstalled() : bool
    {
        return $this->isAppInstalled($this->legacyDir);
    }

    /**
     * Check if is installer locked
     *
     * @return bool is locked
     */
    public function isInstallerLocked() : bool
    {
        return $this->isAppInstallerLocked($this->legacyDir);
    }

    /**
     * Load legacy config
     *
     * @return array|null is locked
     */
    public function loadLegacyConfig() : ?array
    {
        return $this->getLegacyConfig($this->legacyDir);
    }
}
