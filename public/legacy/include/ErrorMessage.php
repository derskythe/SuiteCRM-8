<?php

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

namespace SuiteCRM;

use LoggerManager;
use LoggerTemplate;

if (!defined('sugarEntry') || !sugarEntry) {
    die('Not A Valid Entry Point');
}

#[\AllowDynamicProperties]
class ErrorMessage
{

    /**
     * integer
     */
    public const DEFAULT_CODE = 1;

    /**
     * string
     */
    public const DEFAULT_LOG_LEVEL = LoggerTemplate::DEFAULT_LOG_LEVEL;

    /**
     *
     * @var string
     */
    protected string $message;

    /**
     *
     * @var integer
     */
    protected int $code;

    /**
     *
     * @var string
     */
    protected string $level;

    /**
     *
     * @var boolean
     */
    protected bool $throw;

    /**
     *
     * @param string $message
     * @param integer|null $code
     * @param string $level
     * @param boolean $throw
     */
    public function __construct(string $message = '', int $code = null, string $level = LoggerTemplate::DEFAULT_LOG_LEVEL, bool $throw = true)
    {
        $this->setState($message, $code, $level, $throw);
    }

    /**
     *
     * @param string $message
     * @param integer|null $code
     * @param string $level
     * @param boolean $throw
     */
    public function setState(string $message = '', int $code = null, string $level = LoggerTemplate::DEFAULT_LOG_LEVEL, bool $throw = true): void
    {
        $this->message = $message;
        $this->code = $code;
        $this->level = $level;
        $this->throw = $throw;
    }

    /**
     *
     * @throws ErrorMessageException
     */
    public function handle(): void
    {
        if ($this->level) {
            $log = LoggerManager::getLogger();
            $level = $this->level;
            $log->$level($this->message);
        }
        if ($this->throw) {
            throw new ErrorMessageException($this->message, $this->code);
        }
    }

    /**
     *
     * @param string $message
     * @param string $level
     * @param boolean $throw
     * @param integer $code
     * @throws ErrorMessageException
     */
    public static function handler(string $message, string $level = LoggerTemplate::DEFAULT_LOG_LEVEL, bool $throw = true, int $code = self::DEFAULT_CODE): void
    {
        $errorMessage = new ErrorMessage($message, $code, $level, $throw);
        $errorMessage->handle();
    }

    /**
     *
     * @param string $message
     * @param string $level
     * @throws ErrorMessageException
     */
    public static function log(string $message, string $level = LoggerTemplate::DEFAULT_LOG_LEVEL): void
    {
        self::handler($message, $level, false);
    }

    /**
     *
     * @param string $message
     * @param integer $code
     * @throws ErrorMessageException
     */
    public static function drop(string $message, int $code = self::DEFAULT_CODE): void
    {
        self::handler($message, $code);
    }
}
