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


require_once('include/vCard.php');

class ViewImportvcardsave extends SugarView
{
    public $type = 'save';

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * @see SugarView::display()
     */
    public function display() : void
    {
        $redirect = "index.php?action=Importvcard&module={$_REQUEST['module']}";

        if (!empty($_FILES['vcard']) && $_FILES['vcard']['error'] == 0) {
            $vcard = new vCard();
            $record = $vcard->importVCard($_FILES['vcard']['tmp_name'], $_REQUEST['module']);

            if (empty($record)) {
                SugarApplication::redirect($redirect . '&error=vcardErrorRequired');
            }

            SugarApplication::redirect("index.php?action=DetailView&module={$_REQUEST['module']}&record=$record");
        } else {
            switch ($_FILES['vcard']['error']) {
                case UPLOAD_ERR_FORM_SIZE:
                    $redirect .= '&error=vcardErrorFilesize';
                break;
                default:
                    $redirect .= '&error=vcardErrorDefault';
                    $GLOBALS['log']->error('Upload error code: ' . $_FILES['vcard']['error'] . '. Please refer to the error codes http://php.net/manual/en/features.file-upload.errors.php');
                break;
            }

            SugarApplication::redirect($redirect);
        }
    }
}
