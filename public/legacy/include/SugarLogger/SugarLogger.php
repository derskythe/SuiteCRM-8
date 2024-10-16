<?php
if (!defined('sugarEntry') || !sugarEntry) {
    die('Not A Valid Entry Point');
}
/**
 *
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

/*********************************************************************************
 * Description:  Defines the English language pack for the base application.
 * Portions created by SugarCRM are Copyright (C) SugarCRM, Inc.
 * All Rights Reserved.
 * Contributor(s): ______________________________________..
 ********************************************************************************/
require_once(__DIR__ . '/LoggerManager.php');
require_once(__DIR__ . '/LoggerTemplate.php');

/**
 * Default SugarCRM Logger
 * @api
 */
#[\AllowDynamicProperties]
class SugarLogger implements LoggerTemplate
{
    /**
     * properties for the SugarLogger
     */
    protected string $logfile = LoggerTemplate::DEFAULT_LOGGER_FILE_NAME;
    protected string $ext = LoggerTemplate::DEFAULT_LOGGER_FILE_EXTENSION;
    protected string $dateFormat = LoggerTemplate::DEFAULT_LOGGER_DATE_FORMAT;
    protected string $logSize = LoggerTemplate::DEFAULT_LOGGER_FILE_SIZE;
    protected int $maxLogs = 10;
    protected string $file_suffix = LoggerTemplate::DEFAULT_LOGGER_FILE_SUFFIX;
    protected string $date_suffix = '';
    protected string $log_dir = LoggerTemplate::DEFAULT_LOGGER_LOG_DIR;
    protected mixed $defaultPerms = LoggerTemplate::DEFAULT_LOGGER_DEFAULT_PERMS;

    /**
     * used for config screen
     */
    public static array $filename_suffix = array(
        //bug#50265: Added none option for previous version users
        '' => 'None',
        "MM_yyyy" => 'Month_Year',
        "dd_MM" => 'Day_Month',
        "MM_dd_yyyy" => 'Month_Day_Year',
    );

    /**
     * Let's us know if we've initialized the logger file
     */
    protected bool $initialized = false;

    /**
     * Logger file handle
     */
    protected mixed $fp = null;

    /**
     * @var string
     */
    private string $full_log_file = '';

    public function __get($key)
    {
        return $this->$key;
    }

    /**
     * Used by the diagnostic tools to get SugarLogger log file information
     */
    public function getLogFileNameWithPath(): string
    {
        return $this->full_log_file;
    }

    /**
     * Used by the diagnostic tools to get SugarLogger log file information
     */
    public function getLogFileName(): string
    {
        return ltrim($this->full_log_file, "./");
    }

    /**
     * Constructor
     *
     * Reads the config file for logger settings
     */
    public function __construct()
    {
        $config = SugarConfig::getInstance();
        $this->ext = $config->get('logger.file.ext', $this->ext);
        $this->logfile = $config->get('logger.file.name', $this->logfile);
        $this->dateFormat = $config->get('logger.file.dateFormat', $this->dateFormat);
        $this->logSize = $config->get('logger.file.maxSize', $this->logSize);
        $this->maxLogs = $config->get('logger.file.maxLogs', $this->maxLogs);
        $this->file_suffix = $config->get('logger.file.suffix', $this->file_suffix);
        $this->defaultPerms = $config->get('logger.file.perms', $this->defaultPerms);
        $log_dir = $config->get('log_dir', $this->log_dir);
        if (empty($log_dir)) {
            $this->log_dir = realpath(LoggerTemplate::DEFAULT_LOGGER_LOG_DIR);
        } else {
            // Check for last symbol is not '/'
            $this->log_dir = !str_ends_with($log_dir, '/')
                ? realpath($log_dir)
                : realpath(substr($log_dir, 0, count($log_dir) - 1));
        }
        unset($config);
        $this->_doInitialization();
        LoggerManager::setLogger('default', 'SugarLogger');
    }

    /**
     * @param string $size
     * @return int
     */
    private function parseUnits(string $size): int
    {
        static $units = [
            'k' => 1024,
            'm' => 1024 * 1024,
            'g' => 1024 * 1024 * 1024,
        ];
        if (empty(($size) || strlen($size) < 2)) {
            $size = self::DEFAULT_LOGGER_FILE_SIZE;
        }
        $rollAt = 0;
        if (preg_match('/^\s*([0-9]+\.[0-9]+|\.?[0-9]+)\s*(k|m|g|b)(b?ytes)?/i', $size, $match)) {
            $rollAt = (int)$match[1] * $units[strtolower($match[2])];
        }

        if ($rollAt == 0) {
            // Ooops
            return $units['g'];
        }

        return $rollAt;
    }

    /**
     * Handles the SugarLogger initialization
     */
    protected function _doInitialization(): void
    {
        if ($this->file_suffix && array_key_exists($this->file_suffix, self::$filename_suffix)) {
            //if the global config contains date-format suffix, it will create suffix by parsing datetime
            $this->date_suffix = sprintf('_%s', date($this->file_suffix));
        } else {
            $this->date_suffix = '';
        }
        $this->full_log_file = $this->log_dir . DIRECTORY_SEPARATOR . $this->logfile . $this->date_suffix . $this->ext;
        $this->initialized = $this->_fileCanBeCreatedAndWrittenTo();
        $this->rollLog();
    }

    /**
     * Checks to see if the SugarLogger file can be created and written to
     * @return bool
     */
    protected function _fileCanBeCreatedAndWrittenTo(): bool
    {
        clearstatcache(true, $this->full_log_file);
        if (is_writable($this->full_log_file)) {
            return true;
        }

        if (!is_file($this->full_log_file)) {
            @touch($this->full_log_file);
            if ($this->defaultPerms !== false) {
                @chmod($this->full_log_file, $this->defaultPerms);
            }

            return is_writable($this->full_log_file);
        } else {
            trigger_error("SugarLogger::log() failed to open file pointer for file: {$this->full_log_file}", E_USER_NOTICE);
        }

        return false;
    }

    /**
     * for log() function, it shows a backtrace information in log when
     * the 'show_log_trace' config variable is set and true
     * @return string a readable trace string
     */
    private function getTraceString(): string
    {
        $trace = debug_backtrace();
        if (empty($trace)) {
            return "-empty-\n";
        }
        $ret = array(count($trace) + 2);
        $ret[0] = PHP_EOL;
        for ($i = 0; $i < count($ret); $i++) {
            $call = $trace[$i];

            $file = $call['file'] ?? '???';
            $line = $call['line'] ?? '???';
            $class = $call['class'] ?? '';
            $type = $call['type'] ?? '';
            $function = $call['function'] ?? '???';

            $ret[$i + 1] = sprintf("In %s#%d from %s%s%s(..)", $file, $line, $class, $type, $function);
        }
        $ret[count($ret) - 1] = PHP_EOL;

        return join(PHP_EOL, $ret);
    }

    /**
     * Show log
     * and show a backtrace information in log when
     * the 'show_log_trace' config variable is set and true
     * see LoggerTemplate::log()
     * @param string $method
     * @param array|string $message
     */
    public function log(string $method, array|string $message): void
    {
        global /** @var array $sugar_config */
        $sugar_config;

        if (!$this->initialized) {
            trigger_error("SugarLogger::log() not initialized and failed to log message: {$message}", E_USER_NOTICE);
            return;
        }
        //lets get the current user id or default to -none- if it is not set yet
        $userID = (!empty($GLOBALS['current_user']->id))
            ? $GLOBALS['current_user']->id
            : '-none-';

        //if we haven't opened a file pointer yet let's do that
        if (!isset($this->fp) || empty($this->fp)) {
            $this->fp = fopen($this->full_log_file, 'ab');

            fwrite(
                $this->fp,
                sprintf("[%s] - %s%s",
                    date($this->dateFormat),
                    "Started Logger Write",
                    PHP_EOL));
        }

        $final_message = '';
        // change to a string if there is just one entry
        if (is_array($message) && count($message) == 1) {
            $final_message .= join(PHP_EOL, $message);
        } else if (is_array($message)) {
            // change to a human-readable array output if it's any other array
            $final_message .= print_r($message, true);
        } else {
            $final_message .= $message . PHP_EOL;
        }

        if (isset($sugar_config['show_log_trace']) && $sugar_config['show_log_trace']) {
            $trace = $this->getTraceString();
            $final_message .= (PHP_EOL . $trace);
        }

        //write out to the file including the time in the dateFormat the process id , the user id , and the log level as well as the message
        if (isset($this->fp)) {
            fwrite(
                $this->fp,
                sprintf("[%s] - [%s][%s][%s] | %s%s",
                    date($this->dateFormat),
                    getmypid(),
                    $userID,
                    str_pad(strtoupper($method), 5, ' '),
                    $final_message,
                    PHP_EOL
                )
            );
        } else {
            trigger_error("SugarLogger::log() failed to open file pointer for file: {$this->full_log_file}", E_USER_WARNING);
        }
    }

    /**
     * rolls the logger file to start using a new file
     */
    protected function rollLog($force = false): void
    {
        if (!$this->initialized || empty($this->logSize) || empty($this->full_log_file)) {
            trigger_error("SugarLogger::rollLog() not initialized and failed to roll log file: {$this->full_log_file}", E_USER_NOTICE);
            return;
        }
        // bug#50265: Parse the its unit string and get the size properly

        // check if our log file is greater than that or if we are forcing the log to roll if and only if roll size assigned the value correctly
        $rollAt = $this->parseUnits($this->logSize);
        if (!$force && (filesize($this->full_log_file) < $rollAt)) {
            return;
        }

        //now lets move the logs starting at the oldest and going to the newest
        for ($i = $this->maxLogs - 2; $i > 0; $i--) {
            $old_name = sprintf("%s%s%s_%d%s", $this->log_dir, $this->logfile, $this->date_suffix, $i, $this->ext);
            if (file_exists($old_name)) {
                $to = $i + 1;
                $new_name = sprintf("%s%s%s_%d%s", $this->log_dir, $this->logfile, $this->date_suffix, $to, $this->ext);
                //nsingh- Bug 22548  Win systems fail if new file name already exists. The fix below checks for that.
                //if/else branch is necessary as suggested by someone on php-doc ( see rename function ).
                sugar_rename($old_name, $new_name);

                //rename ( $this->logfile . $i . $this->ext, $this->logfile . $to . $this->ext );
            }
        }
        //now lets move the current .log file
        sugar_rename($this->full_log_file, sprintf("%s%s%s_1%s", $this->log_dir, $this->logfile, $this->date_suffix, $this->ext));
    }

    /**
     * This is needed to prevent un-serialize vulnerability
     * @throws Exception
     */
    public function __wakeup()
    {
        // clean all properties
        foreach (get_object_vars($this) as $k => $v) {
            $this->$k = null;
        }
        trigger_error('SugarLogger::__wakeup() is not allowed', E_USER_NOTICE);
        throw new Exception('Not a serializable object');
    }

    /**
     * Destructor
     *
     * Closes the SugarLogger file handle
     */
    public function __destruct()
    {
        if (!isset($this->fp)) {
            return;
        }

        $this->log('info', 'Shutting down SugarLogger');
        fflush($this->fp);
        fclose($this->fp);
        unset($this->fp);
    }
}
