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




global $current_user;
$dashletData['MyContactsDashlet']['searchFields'] = array('date_entered'     => array('default' => ''),
                                                          'title'             => array('default' => ''),
                                                          'primary_address_country'  => array('default' => ''),
                                                          'assigned_user_id' => array('type'    => 'assigned_user_name',
                                                                                      'default' => $current_user->name,
                                                                                      'label' => 'LBL_ASSIGNED_TO'));
$dashletData['MyContactsDashlet']['columns'] = array('name' => array('width'   => '30',
                                                                     'label'   => 'LBL_NAME',
                                                                     'link'    => true,
                                                                     'default' => true,
                                                                     'related_fields' => array('first_name', 'last_name', 'salutation')),
                                                     'account_name' => array('width' => '20',
                                                                             'label' => 'LBL_ACCOUNT_NAME',
                                                                             'sortable' => false,
                                                                             'link' => true,
                                                                             'module' => 'Accounts',
                                                                             'id' => 'ACCOUNT_ID',
                                                                             'ACLTag' => 'ACCOUNT'),
                                                     'title' => array('width' => '20s',
                                                                      'label' => 'LBL_TITLE',
                                                                      'default' => true),
                                                     'email1' => array('width' => '10',
                                                                    'label' => 'LBL_EMAIL_ADDRESS',
                                                                    'sortable' => false,
                                                                    'customCode' => '{$EMAIL1_LINK}',),
                                                     'phone_work' => array('width'   => '15',
                                                                           'label'   => 'LBL_OFFICE_PHONE',
                                                                           'default' => true),
                                                     'phone_home' => array('width' => '10',
                                                                           'label' => 'LBL_HOME_PHONE'),
                                                     'phone_mobile' => array('width' => '10',
                                                                             'label' => 'LBL_MOBILE_PHONE'),
                                                     'phone_other' => array('width' => '10',
                                                                            'label' => 'LBL_OTHER_PHONE'),
                                                     'date_entered' => array('width'   => '15',
                                                                             'label'   => 'LBL_DATE_ENTERED',
                                                                             'default' => true),
                                                     'date_modified' => array('width'   => '15',
                                                                              'label'   => 'LBL_DATE_MODIFIED'),
                                                     'created_by' => array('width'   => '8',
                                                                           'label'   => 'LBL_CREATED'),
                                                     'assigned_user_name' => array('width'   => '15',
                                                                                   'label'   => 'LBL_LIST_ASSIGNED_USER',
                                                                                   'default' => true),
                                                                             );
