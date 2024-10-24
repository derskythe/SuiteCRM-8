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

if (!defined('sugarEntry') || !sugarEntry) {
    die('Not A Valid Entry Point');
}

use LoggerTemplate;

/**
 * LangText
 *
 * @author gyula
 */
#[\AllowDynamicProperties]
class LangText
{
    /**
     * string
     */
    public const LOG_LEVEL = LoggerTemplate::DEFAULT_LOG_LEVEL;

    /**
     * integer
     */
    public const USING_MOD_STRINGS = 1;

    /**
     * integer
     */
    public const USING_APP_STRINGS = 2;

    /**
     * integer
     */
    public const USING_ALL_STRINGS = 3;

    /**
     *
     * @var string
     */
    protected ?string $key;

    /**
     *
     * @var array
     */
    protected ?array $args;

    /**
     *
     * @var integer
     */
    protected int $use;

    /**
     *
     * @var boolean
     */
    protected bool $log;

    /**
     *
     * @var boolean
     */
    protected bool $to_throw;

    /**
     *
     * @var string
     */
    protected ?string $module;

    /**
     *
     * @var string
     */
    protected ?string $lang;

    /**
     *
     * @param string|null $key
     * @param array|null $args
     * @param integer $use
     * @param boolean $log
     * @param boolean $throw
     * @param string|null $module
     * @param string|null $lang
     */
    public function __construct(
        string $key = null,
        array  $args = null,
        int    $use = self::USING_ALL_STRINGS,
        bool   $log = true,
        bool   $throw = true,
        string $module = null,
        string $lang = null
    )
    {
        $this->key = $key;
        $this->args = $args;
        $this->use = $use;
        $this->log = $log;
        $this->to_throw = $throw;
        $this->module = $module;
        $this->lang = $lang;
    }

    /**
     *
     * @param string|null $key
     * @param array|null $args
     * @param integer|null $use
     * @param string|null $module
     * @param string|null $lang
     *
     * @return string
     * @throws ErrorMessageException
     * @global array $mod_strings
     * @global array $app_strings
     */
    public function getText(
        string $key = null,
        array  $args = null,
        int    $use = null,
        string $module = null,
        string $lang = null
    ) : string
    { // TODO: rename the methode to LangText::translate()

        $this->selfUpdate($key, $args, $use);
        $textResolved = $this->resolveText($module, $lang);

        return $this->replaceArgs($textResolved);
    }

    /**
     *
     * @param string|null $module
     * @param string|null $lang
     *
     * @return string
     * @throws ErrorMessageException
     * @global array $mod_strings
     * @global array $app_list_strings
     * @global array $app_strings
     */
    protected function resolveText(string $module = null, string $lang = null) : ?string
    {
        $textFromGlobals = $this->resolveTextByGlobals();
        $text = $this->updateTextByModuleLang($textFromGlobals, $module, $lang);

        if (!$text) {
            if ($this->log) {
                ErrorMessage::handler(
                    'A language key does not found: [' . $this->key . ']',
                    self::LOG_LEVEL,
                    $this->to_throw
                );
            } else {
                $text = $this->key;
            }
        }

        return $text;
    }

    /**
     *
     * @return string
     * @throws ErrorMessageException
     * @global array $app_list_strings
     * @global array $app_strings
     * @global array $mod_strings
     */
    protected function resolveTextByGlobals() : ?string
    {
        $text = '';
        // TODO: app_strings and mod_strings could be in separated methods
        global $app_strings, $mod_strings, $app_list_strings;

        switch ($this->use) {
            case self::USING_MOD_STRINGS:
                $text = $this->resolveTextByGlobal($mod_strings, $this->key);
                break;
            case self::USING_APP_STRINGS:
                $text = $this->resolveTextByGlobal($app_strings, $this->key);
                break;
            case self::USING_ALL_STRINGS:
                $text = $this->resolveTextByGlobal(
                    $mod_strings,
                    $this->key,
                    $this->resolveTextByGlobal(
                        $app_strings,
                        $this->key,
                        $this->resolveTextByGlobal($app_list_strings, $this->key)
                    )
                );
                break;
            default:
                ErrorMessage::drop('Unknown use case for translation: ' . $this->use);
                break;
        }

        return $text;
    }

    /**
     *
     * @param array $texts
     * @param string $key
     * @param string|null $default
     *
     * @return string
     */
    protected function resolveTextByGlobal(array $texts, string $key, string $default = null) : ?string
    {
        return isset($texts[$key]) && $texts[$key] ? $texts[$key] : $default;
    }

    /**
     *
     * @param string $text
     * @param string|null $module
     * @param string|null $lang
     *
     * @return string
     */
    protected function updateTextByModuleLang(string $text, string $module = null, string $lang = null) : ?string
    {
        $moduleLang = $this->getModuleLang($module, $lang);
        if (!$text && $moduleLang) {
            $text = isset($moduleLang[$this->key]) && $moduleLang[$this->key] ? $moduleLang[$this->key] : null;
        }

        return $text;
    }

    /**
     *
     * @param string $text
     *
     * @return string
     */
    protected function replaceArgs(string $text) : string
    {
        foreach ((array) $this->args as $name => $value) {
            $text = str_replace('{' . $name . '}', $value, $text);
        }

        return $text;
    }

    /**
     *
     * @param string|null $key
     * @param array|null $args
     * @param integer|null $use
     */
    protected function selfUpdate(string $key = null, array $args = null, int $use = null) : void
    {
        if (!is_null($key)) {
            $this->key = $key;
        }

        if (!is_null($args)) {
            $this->args = $args;
        }

        if (!is_null($use)) {
            $this->use = $use;
        }
    }

    /**
     *
     * @param string|null $module
     * @param string|null $lang
     *
     * @return array|null
     */
    protected function getModuleLang(string $module = null, string $lang = null) : ?array
    {
        $moduleLang = null;

        $moduleName = $module ? : $this->module;

        if ($moduleName) {
            // retrieve translation for specified module
            $lang = $lang ? : ($this->lang ? : $GLOBALS['current_language']);
            include_once __DIR__ . '/SugarObjects/LanguageManager.php';
            $moduleLang = \LanguageManager::loadModuleLanguage($moduleName, $lang);
        }

        return $moduleLang;
    }

    /**
     *
     * @return string
     * @throws ErrorMessageException
     */
    public function __toString()
    {
        return $this->getText();
    }

    /**
     *
     * @param string $key
     * @param array|null $args
     * @param boolean|null $log
     * @param boolean $throw
     * @param string|null $module
     * @param string|null $lang
     *
     * @return string
     * @throws ErrorMessageException
     */
    public static function get(
        string $key,
        array  $args = null,
        int    $use = self::USING_ALL_STRINGS,
        bool   $log = true,
        bool   $throw = true,
        string $module = null,
        string $lang = null
    ) : string
    {
        $text = new LangText($key, $args, $use, $log, $throw, $module, $lang);

        return $text->getText();
    }
}
