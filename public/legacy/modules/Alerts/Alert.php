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

#[\AllowDynamicProperties]
class Alert extends Basic
{
    public string $module_dir = 'Alerts';
    public string $object_name = 'Alert';
    public string $table_name = 'alerts';
    public $disable_row_level_security = true ; // to ensure that modules created and deployed under CE will continue to function under team security if the instance is upgraded to PRO
    public $created_by_link;
    public $modified_user_link;
    public string $assigned_user_name;
    public $assigned_user_link;
    public $is_read;
    public $snooze;
    /**
     * @var string
     */
    public $url_redirect;
    /**
     * @var string
     */
    public $type;
    /**
     * @var string
     */
    public $target_module;
    /**
     * @var string
     */
    public $reminder_id;
    /**
     * @var string
     */
    public $date_start;

    public function __construct()
    {
        parent::__construct();
    }

    public function snoozeUntil() {

        global $current_user;

        $preference = $current_user->getPreference('snooze_alert_timer') ?? null;

        $snoozeTimer = $preference;
        if (empty($preference)){
            require_once 'modules/Configurator/Configurator.php';
            $configurator = new Configurator();
            $snoozeTimer = $configurator->config['snooze_alert_timer'] ?? $sugar_config['snooze_alert_timer'] ?? '';
        }

        if (empty($snoozeTimer) || !is_numeric($snoozeTimer)) {
            $snoozeTimer = 600;
        }

        return date('Y-m-d H:i:s', strtotime("+ $snoozeTimer sec"));
    }


    public function bean_implements($interface) : bool
    {
        switch ($interface) {
            case 'ACL': return true;
        }
        return false;
    }
}
