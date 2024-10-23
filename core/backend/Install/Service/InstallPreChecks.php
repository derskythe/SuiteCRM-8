<?php
/** @noinspection CurlSslServerSpoofingInspection */
/** @noinspection PhpElementIsNotAvailableInCurrentPhpVersionInspection */

/**
 *
 * SugarCRM Community Edition is a customer relationship management program developed by
 * SugarCRM, Inc. Copyright (C) 2004-2013 SugarCRM Inc.
 *
 * SuiteCRM is an extension to SugarCRM Community Edition developed by SalesAgility Ltd.
 * Copyright (C) 2011 - 2024 SalesAgility Ltd.
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

namespace App\Install\Service;

use JsonException;
use Twig\Environment;
use AllowDynamicProperties;
use Twig\Error\SyntaxError;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Psr\Log\LoggerInterface;
use Twig\Loader\FilesystemLoader;

// Available from PHP 8.2

#[AllowDynamicProperties]
class InstallPreChecks
{
    public const STREAM_NAME = 'upload';

    /**
     * @var array
     */
    public array $systemChecks = [];

    /**
     * @var bool
     */
    public bool $errorsFound = false;

    /**
     * @var bool
     */
    public bool $warningsFound = false;

    /**
     * @var string
     */
    public string $xsrfToken = '';

    /**
     * @var array
     */
    public array $cookies = [];

    /**
     * @var LoggerInterface
     */
    private LoggerInterface $log;

    /**
     * @var array
     */
    public array $modStrings;

    /**
     * @param string $projectDir
     * @param string $legacyDir
     * @param LoggerInterface $log
     */
    public function __construct(
        LoggerInterface $log,
        string          $projectDir,
        string          $legacyDir
    )
    {
        $this->log = $log;
        $this->projectDir = $projectDir;
        $this->legacyDir = $legacyDir;
        $this->log->info(
            sprintf(
                'Initializing InstallPreChecks%sUsing logger type: %s%sProject dir: %s%sLegacy dir: %s',
                PHP_EOL,
                get_class($this->log),
                PHP_EOL,
                $this->projectDir,
                PHP_EOL,
                $this->legacyDir
            )
        );

        if (!defined('SUGARCRM_MIN_UPLOAD_MAX_FILESIZE_BYTES')) {
            define('SUGARCRM_MIN_UPLOAD_MAX_FILESIZE_BYTES', 6 * 1024 * 1024);
        }

        if (!defined('SUGARCRM_MIN_MEM')) {
            define('SUGARCRM_MIN_MEM', 256 * 1024 * 1024);
        }

        $this->modStrings = $this->getLanguageStrings();
    }

    /**
     * @throws SyntaxError
     * @throws RuntimeError
     * @throws LoaderError
     * @throws JsonException
     */
    public function setupTwigTemplate() : void
    {
        $sugar_config = $this->getConfigValues();

        $cssFile = '';
        $files = scandir('dist');

        foreach ($files as $file) {

            if (preg_match('/styles\.*\.css$/', $file)) {
                $cssFile = $file;
                break;
            }
        }

        if (($sugar_config['installer_locked'] ?? false) === true && file_exists(
                $this->legacyDir . 'legacy/config.php'
            )) {
            $loader = new FilesystemLoader($this->projectDir . 'core/backend/Install/Resources');
            $twig = new Environment($loader);
            $template = $twig->load('installer_locked.html.twig');
            echo $template->render([
                                       'cssFile'     => $cssFile,
                                       'mod_strings' => $this->modStrings
                                   ]);

            return;
        }

        $path = realpath('./');
        $loader = new FilesystemLoader($this->projectDir . 'core/backend/Install/Resources');
        $twig = new Environment($loader);
        $template = $twig->load('install-prechecks.html.twig');

        $this->requiredInstallChecks();
        $this->optionalInstallChecks();

        echo $template->render([
                                   'path'          => $path,
                                   'systemChecks'  => $this->systemChecks,
                                   'errorsFound'   => $this->errorsFound,
                                   'warningsFound' => $this->warningsFound,
                                   'mod_strings'   => $this->modStrings,
                                   'cssFile'       => $cssFile
                               ]);
    }

    /**
     * @return void
     */
    private function requiredInstallChecks() : void
    {
        $labels = [];
        $results = [];

        $this->runServerConfigurationCheck($labels, $results);
        $this->runPHPChecks($labels, $results);

        $key = $this->modStrings['LBL_PERMISSION_CHECKS'];

        $this->log->info('Running Permission Checks');

        $results[] = $this->isWritableDirectories($labels);
        $results[] = $this->isWritableConfigFile($labels);
        $results[] = $this->checkMbStringsModule($labels);
        $results[] = $this->canTouchEnv($labels);

        $this->addChecks($key, $labels, $results);
    }

    /**
     * @param string $sys_php_version
     * @param string $min_php_version
     * @param string $rec_php_version
     *
     * @return int
     */
    private function checkPhpVersion() : int
    {
        $this->log->info('Checking PHP Version');

        require $this->legacyDir . '/php_version.php';

        $sys_php_version = constant('PHP_VERSION');
        $min_php_version = constant('SUITECRM_PHP_MIN_VERSION');
        $rec_php_version = constant('SUITECRM_PHP_REC_VERSION');

        if (version_compare($sys_php_version, $min_php_version, '<') === true) {
            $this->log->error(
                'PHP Version Incompatible, Minimum Version Required:' . $min_php_version .
                'Your Version: ' . $sys_php_version
            );

            return -1;
        }

        if (version_compare($sys_php_version, $rec_php_version, '<') === true) {
            $this->log->info('PHP Version Compatible:' . $sys_php_version);

            return 0;
        }

        $this->log->error(
            'PHP Version Incompatible, Maximum Version Required:' . $rec_php_version .
            'Your Version: ' . $sys_php_version
        );

        return 1;
    }

    /**
     * @param string $baseUrl
     *
     * @return array
     */
    public function checkMainPage(string $baseUrl = '') : array
    {
        $this->log->info('Running curl for SuiteCRM Main Page');
        $ch = curl_init();
        $timeout = 5;
        $logFile = $this->projectDir . '/logs/install.log';
        $checkFile = $this->projectDir . '/.curl_check_main_page';

        file_put_contents($checkFile, 'running');

        $output = [
            'result' => '',
            'errors' => [],
        ];
        if (empty($baseUrl)) {
            $baseUrl =
                ($_SERVER['REQUEST_SCHEME'] ?? 'https') . '://' . $_SERVER['HTTP_HOST'] . ($_SERVER['BASE'] ?? '');
        }
        $baseUrl = rtrim($baseUrl, '/');
        $baseUrl .= '/';
        curl_setopt($ch, CURLOPT_URL, $baseUrl);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_VERBOSE, true);
        ob_start();
        $path = 'php://temp';
        $streamVerboseHandle = fopen($path, 'wb+');
        curl_setopt($ch, CURLOPT_STDERR, $streamVerboseHandle);

        $headers = [];
        curl_setopt(
            $ch, CURLOPT_HEADERFUNCTION,
            static function ($curl, $header) use (&$headers) {
                $len = strlen($header);
                $header = explode(':', $header, 2);
                if (count($header) < 2) // ignore invalid headers
                {
                    return $len;
                }

                $headers[strtolower(trim($header[0]))][] = trim($header[1]);

                return $len;
            }
        );

        $this->log->info('Running curl to get SuiteCRM Instance main page.');

        $result = curl_exec($ch);

        if (curl_errno($ch)) {
            $error = 'cURL error (' . curl_errno($ch) . '): ' . curl_error($ch);

            return $this->outputError($streamVerboseHandle, $error, $logFile, $checkFile, $baseUrl, $result);
        }

        if (!str_contains($result, '<title>SuiteCRM</title>')) {
            $error = $this->modStrings['LBL_NOT_A_VALID_SUITECRM_PAGE'] ?? '';

            return $this->outputError($streamVerboseHandle, $error, $logFile, $checkFile, $baseUrl, $result);
        }

        if (empty($headers['set-cookie'])) {
            $error = $this->modStrings['LBL_NOT_COOKIE_OR_TOKEN'] ?? '';

            return $this->outputError($streamVerboseHandle, $error, $logFile, $checkFile, $baseUrl, $result);
        }

        foreach ($headers['set-cookie'] as $cookie) {
            if (str_contains($cookie, 'XSRF-TOKEN')) {
                $tokenCol = (explode(';', $cookie) ?? [])[0] ?? '';
                $this->xsrfToken = str_replace('XSRF-TOKEN=', '', $tokenCol);
            }
        }

        $this->cookies = $headers['set-cookie'];

        curl_close($ch);

        $this->log->info('Successfully got SuiteCRM instance main page');

        file_put_contents($logFile, stream_get_contents($streamVerboseHandle), FILE_APPEND);

        $output['result'] = $this->modStrings['LBL_CHECKSYS_OK'] ?? 'OK';
        fclose($streamVerboseHandle);

        $debug = ob_get_clean();
        $this->log->info($debug);

        return $output;
    }

    /**
     * @param mixed $streamVerboseHandle
     * @param string $error
     * @param string $logFile
     * @param string $checkFile
     * @param string $baseUrl
     * @param string $result
     *
     * @return array
     */
    private function outputError(
        mixed  $streamVerboseHandle,
        string $error,
        string $logFile,
        string $checkFile,
        string $baseUrl,
        string $result
    ) : array
    {
        $output = [];

        file_put_contents($logFile, stream_get_contents($streamVerboseHandle), FILE_APPEND);
        rewind($streamVerboseHandle);
        if (stream_get_contents($streamVerboseHandle) !== false && !empty(stream_get_contents($streamVerboseHandle))) {
            $this->log->error(stream_get_contents($streamVerboseHandle));
            $output['errors'][] = stream_get_contents($streamVerboseHandle);
        }
        fclose($streamVerboseHandle);
        $debug = ob_get_clean();
        $this->log->error($debug);
        $output['errors'][] = $error;
        $this->log->error($error);
        $output['errors'][] = 'The url used for the call was: ' . $baseUrl;
        $this->log->error('The url used for the call was: ' . $baseUrl);

        if (!empty($result)) {
            $output['errors'][] = $result;
            $this->log->error($result);

            return $output;
        }

        if (file_exists($checkFile)) {
            unlink($checkFile);
        }

        $output['errors'][] = $this->modStrings['LBL_EMPTY'];
        $this->log->error($this->modStrings['LBL_EMPTY']);

        return $output;
    }

    /**
     * @param string $baseUrl
     *
     * @return array
     * @throws JsonException
     */
    public function checkGraphQlAPI(string $baseUrl = '') : array
    {
        $this->log->info('Running curl for Api');
        $ch = curl_init();
        $timeout = 5;
        $logFile = $this->projectDir . '/logs/install.log';
        $checkFile = $this->projectDir . '/.curl_check_main_page';

        if (!file_exists($checkFile)) {
            file_put_contents($checkFile, 'running');
        }

        $output = [
            'result' => '',
            'errors' => []
        ];

        if (empty($baseUrl)) {
            $baseUrl =
                ($_SERVER['REQUEST_SCHEME'] ?? 'https') . '://' . $_SERVER['HTTP_HOST'] . ($_SERVER['BASE'] ?? '');
        }
        $baseUrl = rtrim($baseUrl, '/');
        $baseUrl .= '/';
        $apiUrl = $baseUrl . 'api/graphql';
        $systemConfigApiQuery =
            "{\"operationName\":\"systemConfigs\",\"variables\":{},\"query\":\"query systemConfigs {\n  systemConfigs {\n    edges {\n      node {\n        id\n        _id\n        value\n        items\n        __typename\n      }\n      __typename\n    }\n    __typename\n  }\n}\n\"}";

        curl_setopt($ch, CURLOPT_URL, $apiUrl);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $systemConfigApiQuery);
        curl_setopt($ch, CURLOPT_VERBOSE, true);

        $headers = array();
        $headers[] = 'Content-Type: application/json';
        $headers[] = 'X-Xsrf-Token: ' . $this->xsrfToken;
        $cookieHeader = 'Cookie: ';

        foreach ($this->cookies as $cookie) {
            $cookie = (explode(';', $cookie) ?? [])[0] ?? '';
            $cookieHeader .= $cookie . ';';
        }

        $headers[] = $cookieHeader;
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        ob_start();
        $path = 'php://temp';
        $streamVerboseHandle = fopen($path, 'wb+');
        curl_setopt($ch, CURLOPT_STDERR, $streamVerboseHandle);

        $this->log->info('Calling Graphql api');

        $result = curl_exec($ch);

        if (curl_errno($ch)) {
            $error = 'cURL error (' . curl_errno($ch) . '): ' . curl_error($ch);

            return $this->outputError($streamVerboseHandle, $error, $logFile, $checkFile, $apiUrl, $result);
        }

        $resultJson = json_decode($result, true, 512, JSON_THROW_ON_ERROR);

        if (empty($resultJson)) {
            $error = $this->modStrings['LBL_CURL_JSON_ERROR'] ?? '';

            return $this->outputError($streamVerboseHandle, $error, $logFile, $checkFile, $apiUrl, $result);
        }

        if (empty($resultJson['data']['systemConfigs'])) {
            $error = $this->modStrings['LBL_UNABLE_TO_FIND_SYSTEM_CONFIGS'] ?? '';

            return $this->outputError($streamVerboseHandle, $error, $logFile, $checkFile, $apiUrl, $result);
        }

        if (file_exists($checkFile)) {
            unlink($checkFile);
        }

        curl_close($ch);

        file_put_contents(
            $logFile,
            stream_get_contents($streamVerboseHandle, -1, 0),
            FILE_APPEND
        );
        $output['result'] = $this->modStrings['LBL_CHECKSYS_OK'] ?? 'OK';
        fclose($streamVerboseHandle);
        $debug = ob_get_clean();
        $this->log->info($debug);

        return $output;
    }

    /**
     * @return array
     */
    private function getLanguageStrings() : array
    {
        $mod_strings = [];
        $enUsStrings = [];
        $lang = 'en_us';

        $sugarConfig = $this->getConfigValues();
        $configOverride = $this->getConfigOverrideValues();

        $enUsLangPack = $this->legacyDir . 'install/language/' . $lang . '.lang.php';

        if (is_file($enUsLangPack)) {
            include($enUsLangPack);
            $enUsStrings = $mod_strings;
        }

        if (!empty($sugarConfig['default_language'])) {
            $lang = $sugarConfig['default_language'];
        }

        if (!empty($configOverride['default_language'])) {
            $lang = $configOverride['default_language'];
        }

        $langPack = $this->legacyDir . '/install/language/' . $lang . '.lang.php';

        if (($langPack !== $enUsLangPack) && file_exists($langPack)) {
            include($langPack);
            $mod_strings = array_merge($enUsStrings, $mod_strings);
        }

        return $mod_strings;
    }

    /**
     * @return array
     */
    private function getConfigValues() : array
    {
        $sugar_config = [];

        $configFile = $this->legacyDir . '/legacy/config.php';

        if (file_exists($configFile)) {
            include($configFile);
        }

        return $sugar_config;
    }

    /**
     * @return array
     */
    private function getConfigOverrideValues() : array
    {
        $sugar_config = [];

        $configOverrideFile = $this->legacyDir . '/config_override.php';

        if (file_exists($configOverrideFile)) {
            include($configOverrideFile);
        }

        return $sugar_config;
    }

    /**
     * @param $labels
     * @param $results
     *
     * @return void
     */
    private function runPHPChecks($labels, $results) : void
    {
        $key = $this->modStrings['LBL_PHP_CHECKS'];

        $this->log->info('Starting PHP Checks');

        $results[] = $this->checkSystemPhpVersion($labels);
        $results[] = $this->checkMemoryLimit($labels);
        $results[] = $this->checkAllowsStream($labels);

        $this->addChecks($key, $labels, $results);
    }

    /**
     * @param $labels
     *
     * @return array
     */
    private function checkMemoryLimit(array &$labels) : array
    {
        $this->log->info('Checking PHP Memory Limit');

        $labels[] = $this->modStrings['LBL_CHECKSYS_MEM'];

        $results = [
            'result' => '',
            'errors' => []
        ];

        $memoryLimit = $this->returnBytes(ini_get('memory_limit') ?? '');
        if ($memoryLimit < 0.1) {
            $this->log->info('Memory is set to Unlimited');
            $results['result'] = $this->modStrings['LBL_CHECKSYS_MEM_UNLIMITED'];

            return $results;
        }

        if ((int) $memoryLimit < (int) constant('SUGARCRM_MIN_MEM')) {
            $minMemoryInMegs = constant('SUGARCRM_MIN_MEM') / (1024 * 1024);
            $error =
                $this->modStrings['LBL_PHP_MEM_1'] . $memoryLimit . $this->modStrings['LBL_PHP_MEM_2'] . $minMemoryInMegs . $this->modStrings['LBL_PHP_MEM_3'];
            $results['errors'][] = $this->modStrings['LBL_CHECK_FAILED'] . $error;

            return $results;
        }

        $this->log->info('PHP Memory Limit in demand range.');
        $results['result'] = $this->modStrings['LBL_CHECKSYS_OK'];

        return $results;
    }

    /**
     * @param array $labels
     *
     * @return array
     */
    private function checkAllowsStream(array &$labels) : array
    {
        $this->log->info('Checking does Sushosin allow to use upload');

        $labels[] = $this->modStrings['LBL_STREAM'];

        $results = [
            'result' => '',
            'errors' => []
        ];

        if ($this->getSuhosinStatus() === true || (str_contains(ini_get('suhosin.perdir'), 'e')
                && !str_contains((string) $_SERVER['SERVER_SOFTWARE'], 'Microsoft-IIS'))) {
            $this->log->info('Sushosin allows use of Upload');
            $results['result'] = $this->modStrings['LBL_CHECKSYS_OK'];

            return $results;
        }

        $this->log->error($this->modStrings['ERR_SUHOSIN']);
        $results['errors'][] = $this->modStrings['ERR_SUHOSIN'];

        return $results;
    }

    /**
     * @return bool
     */
    public function getSuhosinStatus() : bool
    {
        if (!extension_loaded('suhosin')) {
            return true;
        }

        $configuration = ini_get_all('suhosin', false);

        if ($configuration['suhosin.simulation']) {
            return true;
        }

        $streams = $configuration['suhosin.executor.include.whitelist'];

        if ($streams !== '') {
            $streams = explode(',', $streams);
            foreach ($streams as $stream) {
                $stream = explode('://', $stream, 2);
                if (count($stream) === 1) {
                    if ($stream[0] === self::STREAM_NAME) {
                        return true;
                    }
                } elseif ($stream[1] === '' && $stream[0] === self::STREAM_NAME) {
                    return true;
                }
            }
            $this->log->error(
                'Stream ' . self::STREAM_NAME .
                ' is not listed in suhosin.executor.include.whitelist and blocked because of it'
            );

            return false;
        }

        $streams = $configuration['suhosin.executor.include.blacklist'];
        if ($streams !== '') {
            $streams = explode(',', $streams);
            foreach ($streams as $stream) {
                $stream = explode('://', $stream, 2);
                if ($stream[0] === self::STREAM_NAME) {
                    $this->log->error(
                        'Stream ' . self::STREAM_NAME .
                        'is listed in suhosin.executor.include.blacklist and blocked because of it'
                    );

                    return false;
                }
            }

            return true;
        }

        $this->log->error(
            'Suhosin blocks all streams, please define ' . self::STREAM_NAME .
            ' stream in suhosin.executor.include.whitelist'
        );

        return false;
    }

    /**
     * @param array $labels
     *
     * @return array
     */
    private function isWritableDirectories(array &$labels) : array
    {
        $this->log->info('Checking for Dirs are writable');

        $labels[] = $this->modStrings['LBL_CHECKSYS_LEGACY_CACHE'];

        $results = [
            'result' => '',
            'errors' => [],
        ];

        $cacheFiles = [
            'root'         => [
                'path'       => '',
                'message_id' => 'Root Dir',
            ],
            'logs'         => [
                'path'       => '/logs',
                'message_id' => 'Logs Dir',
            ],
            'cache_themes' => [
                'path'       => '/cache',
                'message_id' => 'Cache Root Dir',
            ],
            'extensions'   => [
                'path'       => '/extensions',
                'message_id' => 'Extensions Dir',
            ],
            'secrets'      => [
                'path'       => '/config/secrets',
                'message_id' => 'Secrets Config Dir',
            ],
            'cache'        => [
                'path'       => '/legacy/cache',
                'message_id' => 'Cache root Dir',
            ],
            'images'       => [
                'path'       => '/legacy/cache/images',
                'message_id' => 'Images Dir',
            ],
            'layout'       => [
                'path'       => '/legacy/cache/layout',
                'message_id' => 'Layout Dir'
            ],
            'pdf'          => [
                'path'       => '/legacy/cache/pdf',
                'message_id' => 'PDF Dir'
            ],
            'xml'          => [
                'path'       => '/legacy/cache/xml',
                'message_id' => 'XML Dir'
            ],
            'javascript'   => [
                'path'       => '/legacy/cache/javascript',
                'message_id' => 'JavaScript Dir'
            ],
            'upload'       => [
                'path'       => '/legacy/upload',
                'message_id' => 'Upload Dir',
            ],
            'custom'       => [
                'path'       => '/legacy/custom',
                'message_id' => 'Custom Dir',
            ],
            'modules'      => [
                'path'       => '/legacy/modules',
                'message_id' => 'Custom Dir',
            ]
        ];

        $fileList = '';

        foreach ($cacheFiles as $key => $item) {

            $checkName = $item['message_id'] === '' ? $item['path'] : $item['message_id'];
            $this->log->info('Checking if ' . $checkName . ' is writable');

            $dirname = $this->projectDir . $item['path'];
            $isWritable = true;

            if ((is_dir($dirname))) {
                $isWritable = is_writable($dirname);
            }
            if (!$isWritable) {
                $fileList .= '<br />' . $dirname;
                $results['errors'][] = $dirname . ' is Not Writeable.';
                $this->log->error($checkName . ' is Not Writeable');
            }
        }

        if ($fileList !== '') {
            $results['errors'][] = $this->modStrings['ERR_CHECKSYS_FILES_NOT_WRITABLE'];

            return $results;
        }
        $this->log->info('All Dirs is writable');
        $results['result'] = $this->modStrings['LBL_CHECKSYS_OK'];

        return $results;
    }

    /**
     * @param array $labels
     *
     * @return array
     */
    private function checkMbStringsModule(array &$labels) : array
    {
        $this->log->info('Checking MB Module Strings');

        $labels[] = $this->modStrings['LBL_CHECKSYS_MBSTRING'];
        $results = [
            'result' => '',
            'errors' => [],
        ];

        if (!function_exists('mb_strlen')) {
            $this->log->error($this->modStrings['ERR_CHECKSYS_MBSTRING']);
            $results['errors'][] = $this->modStrings['ERR_CHECKSYS_MBSTRING'];

            return $results;
        }

        $this->log->info('mbstrings found');
        $results['result'] = $this->modStrings['LBL_CHECKSYS_OK'];

        return $results;
    }

    /**
     * @param array $labels
     *
     * @return array
     */
    private function isWritableConfigFile(array &$labels) : array
    {
        $this->log->info('Checking if config.php is writable');

        $labels[] = $this->modStrings['LBL_CHECKSYS_CONFIG'];
        $results = [
            'result' => '',
            'errors' => [],
        ];

        if (!is_file($this->legacyDir . 'legacy/config.php')) {
            $this->log->error(
                $this->modStrings['ERR_CHECKSYS_CONFIG_NOT_FOUND'] .
                'Path Checked: legacy/config.php'
            );
            $results['result'] = $this->modStrings['ERR_CHECKSYS_CONFIG_NOT_FOUND'];

            return $results;
        }

        if (!is_writable($this->legacyDir . 'legacy/config.php')) {
            $this->log->error(
                $this->modStrings['ERR_CHECKSYS_CONFIG_NOT_WRITABLE'] .
                'Path Checked: legacy/config.php'
            );
            $results['errors'][] =
                $this->modStrings['ERR_CHECKSYS_CONFIG_NOT_WRITABLE'] .
                ' Path Checked: legacy/config.php';

            return $results;
        }

        $this->log->info('config.php is writable');
        $results['result'] = $this->modStrings['LBL_CHECKSYS_OK'];

        return $results;
    }

    /**
     * @param array $labels
     *
     * @return array
     */
    private function checkXMLParsing(array &$labels) : array
    {
        $this->log->info('Checking XML Parsing');

        $results = [
            'result' => '',
            'errors' => []
        ];

        $labels[] = $this->modStrings['LBL_CHECKSYS_XML'];

        if (!function_exists('xml_parser_create')) {
            $this->log->error($this->modStrings['LBL_CHECKSYS_XML_NOT_AVAILABLE']);
            $results['errors'][] = $this->modStrings['LBL_CHECKSYS_XML_NOT_AVAILABLE'];

            return $results;
        }

        $this->log->info('XML Parser Libraries Found');
        $results['result'] = $this->modStrings['LBL_CHECKSYS_OK'];

        return $results;
    }

    /**
     * @param array $labels
     * @param array $results
     *
     * @return void
     */
    private function checkRequiredModulesInExtensions(array &$labels, array &$results) : void
    {
        $this->log->info('Checking required loaded extensions');

        $modules = [
            'intl',
            'curl',
            'json',
            'gd',
            'mbstring',
            'mysqli',
            'pdo_mysql',
            'openssl',
            'soap',
            'xml',
            'zip',
        ];

        $loadedExtensions = get_loaded_extensions();

        foreach ($modules as $module) {

            $result = [
                'result' => '',
                'errors' => []
            ];

            $labels[] = $this->modStrings['LBL_CHECKSYS_' . strtoupper($module) . '_EXTENSIONS'];
            $this->log->info('Checking if ' . $module . ' exists in loaded extensions');
            if (!in_array($module, $loadedExtensions)) {
                $this->log->error($module . 'not found in extensions.');
                $result['errors'][] =
                    $this->modStrings['ERR_CHECKSYS_' . strtoupper(
                        $module
                    )] ?? $this->modStrings['LBL_CHECKSYS_' . strtoupper($module) . '_NOT_AVAILABLE'];
                $results[] = $result;
                continue;
            }
            $this->log->info($module . ' found in loaded extensions');
            $result['result'] = $this->modStrings['LBL_CHECKSYS_OK'];
            $results[] = $result;
        }
    }

    /**
     * @param array $labels
     *
     * @return array
     */
    private function checkPCRELibrary(array &$labels) : array
    {
        $this->log->info('Checking PCRE Library');

        $results = [
            'result' => '',
            'errors' => []
        ];

        $labels[] = $this->modStrings['LBL_CHECKSYS_PCRE'];

        if (!defined('PCRE_VERSION')) {
            $this->log->error($this->modStrings['ERR_CHECKSYS_PCRE']);
            $results['errors'][] = $this->modStrings['ERR_CHECKSYS_PCRE'];

            return $results;
        }

        if (version_compare(PCRE_VERSION, '7.0') < 0) {
            $this->log->error($this->modStrings['ERR_CHECKSYS_PCRE_VER']);
            $results['errors'][] = $this->modStrings['ERR_CHECKSYS_PCRE_VER'];

            return $results;
        }

        $this->log->info('PCRE Library Exists');
        $results['result'] = $this->modStrings['LBL_CHECKSYS_OK'];

        return $results;
    }

    /**
     * @param array $labels
     *
     * @return array
     */
    private function checkSpriteSupport(array &$labels) : array
    {
        $this->log->info('Checking for GD Library');

        $results = [
            'result' => '',
            'errors' => []
        ];

        $labels[] = $this->modStrings['LBL_SPRITE_SUPPORT'];

        if (!function_exists('imagecreatetruecolor')) {
            $this->log->error($this->modStrings['ERROR_SPRITE_SUPPORT']);
            $results['errors'][] = $this->modStrings['ERROR_SPRITE_SUPPORT'];

            return $results;
        }

        $this->log->info('GD Library Found');
        $results['result'] = $this->modStrings['LBL_CHECKSYS_OK'];

        return $results;
    }

    /**
     * @param array $labels
     *
     * @return array
     */
    private function checkUploadFileSize(array &$labels) : array
    {
        $this->log->info('Checking Upload File Size');

        $results = [
            'result' => '',
            'errors' => []
        ];

        $labels[] = $this->modStrings['LBL_UPLOAD_MAX_FILESIZE_TITLE'];

        $uploadMaxFileSize = ini_get('upload_max_filesize');
        $this->log->info('Upload File Size:' . $uploadMaxFileSize);
        $uploadMaxFileSizeBytes = $this->returnBytes($uploadMaxFileSize);

        if (!($uploadMaxFileSizeBytes >= constant('SUGARCRM_MIN_UPLOAD_MAX_FILESIZE_BYTES'))) {
            $this->log->error($this->modStrings['ERR_UPLOAD_MAX_FILESIZE']);
            $results['errors'][] =
                $this->modStrings['LBL_CHECK_FAILED'] . $this->modStrings['ERR_UPLOAD_MAX_FILESIZE'] . '. Currently yours is: ' . $uploadMaxFileSize;

            return $results;
        }

        $this->log->info(
            'Upload File Size more than ' .
            constant('SUGARCRM_MIN_UPLOAD_MAX_FILESIZE_BYTES')
        );
        $results['result'] = $this->modStrings['LBL_CHECKSYS_OK'];

        return $results;
    }

    /**
     * @param string $val
     *
     * @return float
     */
    private function returnBytes(string $val) : float
    {
        preg_match(
            '/^\s*([\d.,]+)\s*([KMGTPE])[BI]?\w*\s*$/',
            strtoupper($val),
            $matches
        );
        $num = (float) $matches[1];

        switch ($matches[2]) {
            case 'T':
                $num *= 1024;
            // no break
            case 'G':
                $num *= 1024;
            // no break
            case 'M':
                $num *= 1024;
            // no break
            case 'K':
                $num *= 1024;
        }

        return $num;
    }

    /**
     * @param array $labels
     * @param array $results
     *
     * @return void
     */
    private function runServerConfigurationCheck(array $labels, array $results) : void
    {
        $this->log->info('Starting Server Checks');

        $key = $this->modStrings['LBL_SERVER_CHECKS'];

        $results[] = $this->checkXMLParsing($labels);
        $results[] = $this->checkUploadFileSize($labels);
        $results[] = $this->checkPCRELibrary($labels);
        $results[] = $this->checkSpriteSupport($labels);
        $this->checkRequiredModulesInExtensions($labels, $results);

        $this->addChecks($key, $labels, $results);
    }

    /**
     * @param array $labels
     *
     * @return array
     */
    private function checkSystemPhpVersion(array &$labels) : array
    {
        $labels[] = $this->modStrings['LBL_CHECKSYS_PHPVER'];

        $results = [
            'result' => '',
            'errors' => []
        ];

        if ($this->checkPhpVersion() === -1) {
            $results['errors'][] = $this->modStrings['ERR_CHECKSYS_PHP_INVALID_VER'] .
                constant('PHP_VERSION');

            return $results;
        }

        $results['result'] = $this->modStrings['LBL_CHECKSYS_OK'];

        return $results;
    }

    /**
     * @param string $key
     * @param array $labels
     * @param array $results
     * @param bool $optional
     *
     * @return void
     */
    private function addChecks(
        string $key,
        array  $labels,
        array  $results,
        bool   $optional = false
    ) : void
    {
        $this->systemChecks[$key] = [
            'label'  => '',
            'checks' => [
            ]
        ];

        foreach ($labels as $i => $label) {

            $result = $results[$i] ?? '';
            $this->systemChecks[$key]['label'] = $key;
            $this->systemChecks[$key]['checks'][$label]['label'] = $label;
            $this->systemChecks[$key]['checks'][$label]['result'] = $result['result'] ?? '';
            if ($optional) {
                $this->systemChecks[$key]['checks'][$label]['warnings'] = $result['errors'];
                continue;
            }
            $this->systemChecks[$key]['checks'][$label]['errors'] = $result['errors'] ?? [];

        }

        foreach ($results as $result) {

            if (isset($result['result'])
                && $result['result'] !== $this->modStrings['LBL_CHECKSYS_OK']
                && $result['result'] !== $this->modStrings['LBL_CHECKSYS_MEM_UNLIMITED']
                && $result['result'] !== $this->modStrings['ERR_CHECKSYS_CONFIG_NOT_FOUND']
                && $optional !== true
            ) {
                $this->errorsFound = true;
            }

            if ($optional && $result['errors']) {
                $this->warningsFound = true;
            }
        }

    }

    /**
     * @return void
     * @throws JsonException
     */
    private function optionalInstallChecks() : void
    {
        $this->checkOptionalModulesInExtensions();

        $labels = [
            $this->modStrings['LBL_CURL_REQUEST_MAIN_PAGE'],
            $this->modStrings['LBL_CURL_REQUEST_API_PAGE']
        ];

        $result[] = $this->checkMainPage();
        $result[] = $this->checkGraphQlAPI();
        $this->addChecks(
            $this->modStrings['LBL_ROUTE_ACCESS_CHECK'],
            $labels,
            $result,
            true
        );
    }

    /**
     * @return void
     */
    private function checkOptionalModulesInExtensions() : void
    {
        $this->log->info('Checking optional loaded extensions');

        $modules = [
            'imap',
            'ldap',
        ];

        $key = 'SERVER CHECKS';

        $loadedExtensions = get_loaded_extensions();

        foreach ($modules as $module) {
            $result['warnings'] = [];
            $label = $this->modStrings['LBL_CHECKSYS_' . strtoupper($module) . '_EXTENSIONS'];
            $this->systemChecks[$key]['checks'][$label]['label'] = $label;
            $this->log->info('Checking if ' . $module . ' exists in loaded extensions');
            if (!in_array($module, $loadedExtensions)) {
                $result['result'] = '';
                $result['warnings'][] =
                    $this->modStrings['ERR_CHECKSYS_' . strtoupper($module)] ?? strtoupper(
                    $module
                ) . ' not found in extensions.';
                $this->systemChecks[$key]['checks'][$label]['warnings'] = $result['warnings'];
                $this->warningsFound = true;
            } else {
                $this->log->info($module . ' found in loaded extensions');
                $result['result'] = $this->modStrings['LBL_CHECKSYS_OK'];
            }

            $this->systemChecks[$key]['checks'][$label]['result'] = $result['result'];
        }
    }

    /**
     * @param array $labels
     *
     * @return array
     */
    private function canTouchEnv(array &$labels) : array
    {
        $labels[] = $this->modStrings['LBL_CHECKSYS_ENV'];

        $env = $this->projectDir . '/.env';

        $results = [
            'result' => '',
            'errors' => []
        ];

        if ((file_exists($env) && is_writable($env)) || (!file_exists($env) && touch($env))) {
            $this->log->info('.env exists or is writable');
            $results['result'] = $this->modStrings['LBL_CHECKSYS_OK'];

            return $results;
        }

        $results['errors'][] = $this->modStrings['ERR_CHECKSYS_ENV_NOT_WRITABLE'];

        return $results;
    }
}
