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



$subpanel_layout = array(
    //Removed button because this layout def is a component of
    //the activities sub-panel.

    'where' => '',


    'list_fields' => array(
        'object_image'=>array(
            'vname' => 'LBL_OBJECT_IMAGE',
            'widget_class' => 'SubPanelIcon',
            'width' => '2%',
        ),
        'name'=>array(
             'vname' => 'LBL_LIST_SUBJECT',
             'widget_class' => 'SubPanelDetailViewLink',
             'width' => '30%',
        ),
        'status'=>array(
             'widget_class' => 'SubPanelActivitiesStatusField',
             'vname' => 'LBL_LIST_STATUS',
             'width' => '15%',
        ),
        'contact_name'=>array(
             'widget_class' => 'SubPanelDetailViewLink',
             'target_record_key' => 'contact_id',
             'target_module' => 'Contacts',
             'module' => 'Contacts',
             'vname' => 'LBL_LIST_CONTACT',
             'width' => '11%',
        ),

        'parent_name'=>array(
             'vname' => 'LBL_LIST_RELATED_TO',
             'width' => '22%',
             'target_record_key' => 'parent_id',
             'target_module_key'=>'parent_type',
             'widget_class' => 'SubPanelDetailViewLink',
             'sortable'=>false,
        ),
        'date_modified'=>array(
             'vname' => 'LBL_LIST_DATE_MODIFIED',
             'width' => '10%',
        ),
        'edit_button'=>array(
            'vname' => 'LBL_EDIT_BUTTON',
             'widget_class' => 'SubPanelEditButton',
             'width' => '2%',
        ),
        'remove_button'=>array(
            'vname' => 'LBL_REMOVE',
             'widget_class' => 'SubPanelRemoveButton',
             'width' => '2%',
        ),
        'parent_id'=>array(
            'usage'=>'query_only',
        ),
        'parent_type'=>array(
            'usage'=>'query_only',
        ),
        'filename'=>array(
            'usage'=>'query_only',
            'force_exists'=>true
            ),


    ),
);
