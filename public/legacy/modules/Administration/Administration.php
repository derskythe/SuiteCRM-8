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

if (!defined('sugarEntry') || !sugarEntry) {
    die('Not A Valid Entry Point');
}

require_once(__DIR__.'/../../include/database/DatabasePDOManager.php');
require_once('data/SugarBean.php');
require_once('include/OutboundEmail/OutboundEmail.php');

use SuiteCRM\database\DatabasePDOManager;

/**
 * Administration
 */
#[\AllowDynamicProperties]
class Administration extends SugarBean
{
    /**
     * @var array
     */
    public array $settings = [];
    /**
     * @var string
     */
    public $table_name = 'config';
    /**
     * @var string
     */
    public $object_name = 'Administration';
    /**
     * @var bool
     */
    public bool $new_schema = true;
    /**
     * @var string
     */
    public $module_dir = 'Administration';
    public array $config_categories = [
        // 'mail', // cn: moved to include/OutboundEmail
        'disclosure', // appended to all outbound emails
        'notify',
        'system',
        'portal',
        'proxy',
        'massemailer',
        'ldap',
        'captcha',
        'sugarpdf',
    ];
    /**
     * @var bool
     */
    public $disable_custom_fields = true;
    /**
     * @var array|string[]
     */
    public array $checkboxFields = [
        'notify_send_by_default',
        'mail_smtpauth_req',
        'notify_on',
        'portal_on',
        'system_mailmerge_on',
        'proxy_auth',
        'proxy_on',
        'system_ldap_enabled',
        'captcha_on'
    ];

    /**
     *
     * @throws Exception
     */
    public function __construct()
    {
        parent::__construct();

        $this->setupCustomFields('Administration');
    }

    /**
     * @param bool $display_warning
     * @return bool
     * @throws Exception
     */
    public function checkSmtpError(bool $display_warning = true): bool
    {
        global $sugar_config;

        $smtp_error = false;
        $this->retrieveSettings();

        if (!$sugar_config['email_warning_notifications']) {
            $display_warning = false;
        }

        //If sendmail has been configured by setting the config variable ignore this warning
        $sendmail_enabled = isset($sugar_config['allow_sendmail_outbound']) && $sugar_config['allow_sendmail_outbound'];

        // remove php notice from installer
        if (!array_key_exists('mail_smtpserver', $this->settings)) {
            $this->settings['mail_smtpserver'] = '';
        }

        if (trim((string)$this->settings['mail_smtpserver']) === '' && !$sendmail_enabled) {
            if (isset($this->settings['notify_on']) && $this->settings['notify_on']) {
                $smtp_error = true;
            }
        }

        if ($display_warning && $smtp_error) {
            displayAdminError(translate('WARN_NO_SMTP_SERVER_AVAILABLE_ERROR', 'Administration'));
        }

        return $smtp_error;
    }

    /**
     * @param bool $category
     * @param bool $clean
     * @return $this|null
     * @throws Exception
     */
    public function retrieveSettings(bool $category = false, bool $clean = false) : Administration|null
    {
        if(empty($this->db->database) && !DatabasePDOManager::isInit()){
            return null;
        }

        if(!DatabasePDOManager::isInit())
        {
            $quoted_category = $this->db->quote($category);
        }

        // declare a cache for all settings
        $settings_cache = sugar_cache_retrieve('admin_settings_cache');

        if ($clean) {
            $settings_cache = array();
        }

        // Check for a cache hit
        if (!empty($settings_cache)) {
            $this->settings = $settings_cache;
            if (!empty($this->settings[$category])) {
                return $this;
            }
        }

        if (DatabasePDOManager::isInit()) {
            $parameters = array();
            $query = 'SELECT category, name, value FROM :table_name ';
            $parameters['table_name'] = $this->table_name;
            if (!empty($category)) {
                $query.= ' WHERE category = :category';
                $parameters['category'] = $category;
            }

            $result = $this->pdo->executeQueryResult($query, $parameters);
        } else {
            if (!empty($category)) {
                $query = "SELECT category, name, value FROM {$this->table_name} WHERE category = '$quoted_category'";
            } else {
                $query = "SELECT category, name, value FROM {$this->table_name}";
            }

            $result = $this->db->query($query, true, 'Unable to retrieve system settings');

            if (empty($result)) {
                return null;
            }
        }
        while ($row = (DatabasePDOManager::isInit() ? $this->pdo->fetchAssoc($result) : $this->db->fetchByAssoc($result))) {
            if ($this->isPassword($row['category'], $row['name'])) {
                $this->settings[$row['category'] . '_' . $row['name']] = $this->decrypt_after_retrieve($row['value']);
            } else {
                $this->settings[$row['category'] . '_' . $row['name']] = $row['value'];
            }
            $this->settings[$row['category']] = true;
        }
        $this->settings[$category] = true;

        if (!isset($this->settings['mail_sendtype'])) {
            // outbound email settings
            $oe = new OutboundEmail();
            $oe->getSystemMailerSettings();

            foreach ($oe->field_defs as $def => $value) {
                // fixes installer php notice
                if (!array_key_exists($def, $this->settings)) {
                    $this->settings[$def] = '';
                }

                if (str_contains((string)$def, 'mail_')) {
                    $this->settings[$def] = $value;
                }
                if (str_contains((string)$def, 'smtp')) {
                    $this->settings[$def] = $value;
                }
            }
        }

        // At this point, we have built a new array that should be cached.
        sugar_cache_put('admin_settings_cache', $this->settings);

        return $this;
    }

    /**
     * @return void
     */
    public function saveConfig(): void
    {
        // outbound email settings
        $oe = new OutboundEmail();

        foreach ($_GET as $key => $val) {
            $prefix = $this->getConfigPrefix($key);
            if (in_array($prefix[0], $this->config_categories, true)) {
                if (is_array($val)) {
                    $val = implode(',', $val);
                }
                $this->saveSetting($prefix[0], $prefix[1], trim($val));
            }
            if (str_contains($key, 'mail_')) {
                if (in_array($key, $oe->field_defs, true)) {
                    $oe->$key = trim($val);
                }
            }
        }

        // saving outbound email from here is probably redundant, adding a check to make sure
        // SMTP server name is set.
        if (!empty($oe->mail_smtpserver)) {
            $oe->saveSystem();
        }

        $this->retrieveSettings(false, true);
    }

    /**
     * @param string $category
     * @param string $key
     * @param string $value
     * @return int
     */
    public function saveSetting(string $category, string $key, string $value): int
    {
        global $current_user;
        $quoted_category = $this->db->quote($category);
        $quoted_key = $this->db->quote($key);
        $quoted_value = $this->db->quote($value);

        $result = $this->db->query("SELECT count(*) AS the_count FROM config WHERE category = '$quoted_category' AND name = '$quoted_key'");
        $row = $this->db->fetchByAssoc($result);
        $row_count = $row['the_count'];

        if ($this->isPassword($category, $quoted_key)) {
            $quoted_value = $this->encrpyt_before_save($value);
        }

        if ($row_count === 0) {
            $result = $this->db->query("INSERT INTO config (value, category, name) VALUES ('$quoted_value','$quoted_category', '$quoted_key')");
        } else {
            $result = $this->db->query("UPDATE config SET value = '$quoted_value' WHERE category = '$quoted_category' AND name = '$quoted_key'");
        }
        sugar_cache_clear('admin_settings_cache');
        require_once "include/portability/Services/Cache/CacheManager.php";
        (new CacheManager())->markAsNeedsUpdate('app-metadata-navigation-'.$current_user->id);

        return $this->db->getAffectedRowCount($result);
    }

    /**
     * @param mixed $str
     * @return array|false[]
     */
    public function getConfigPrefix(mixed $str): array
    {
        return $str
            ? array(substr((string) $str, 0, strpos((string) $str, '_')), substr((string) $str, strpos((string) $str, '_') + 1))
            : array(false, false);
    }

    /**
     * @param string $category
     * @param string $name
     * @return bool
     */
    public function isPassword(string $category, string $name): bool
    {
        return ($category . '_' . $name === 'ldap_admin_password')
            || ($category . '_' . $name === 'proxy_password');
    }
}
