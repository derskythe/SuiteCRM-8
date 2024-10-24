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


include(__DIR__.'/../metadata/accounts_bugsMetaData.php');
include(__DIR__.'/../metadata/accounts_casesMetaData.php');
include(__DIR__.'/../metadata/accounts_contactsMetaData.php');
include(__DIR__.'/../metadata/accounts_opportunitiesMetaData.php');
include(__DIR__.'/../metadata/calls_contactsMetaData.php');
include(__DIR__.'/../metadata/calls_usersMetaData.php');
include(__DIR__.'/../metadata/calls_leadsMetaData.php');
include(__DIR__.'/../metadata/cases_bugsMetaData.php');
include(__DIR__.'/../metadata/contacts_bugsMetaData.php');
include(__DIR__.'/../metadata/contacts_casesMetaData.php');
include(__DIR__.'/../metadata/configMetaData.php');
include(__DIR__.'/../metadata/contacts_usersMetaData.php');
include(__DIR__.'/../metadata/custom_fieldsMetaData.php');
include(__DIR__.'/../metadata/email_addressesMetaData.php');
include(__DIR__.'/../metadata/emails_beansMetaData.php');
include(__DIR__.'/../metadata/foldersMetaData.php');
include(__DIR__.'/../metadata/import_mapsMetaData.php');
include(__DIR__.'/../metadata/meetings_contactsMetaData.php');
include(__DIR__.'/../metadata/meetings_usersMetaData.php');
include(__DIR__.'/../metadata/meetings_leadsMetaData.php');
include(__DIR__.'/../metadata/opportunities_contactsMetaData.php');
include(__DIR__.'/../metadata/user_feedsMetaData.php');
include(__DIR__.'/../metadata/users_passwordLinkMetaData.php');
include(__DIR__.'/../metadata/prospect_list_campaignsMetaData.php');
include(__DIR__.'/../metadata/prospect_lists_prospectsMetaData.php');
include(__DIR__.'/../metadata/roles_modulesMetaData.php');
include(__DIR__.'/../metadata/roles_usersMetaData.php');
//include("metadata/project_relationMetaData.php");
include(__DIR__.'/../metadata/outboundEmailMetaData.php');
include(__DIR__.'/../metadata/addressBookMetaData.php');
include(__DIR__.'/../metadata/project_bugsMetaData.php');
include(__DIR__.'/../metadata/project_casesMetaData.php');
include(__DIR__.'/../metadata/project_productsMetaData.php');
include(__DIR__.'/../metadata/projects_accountsMetaData.php');
include(__DIR__.'/../metadata/projects_contactsMetaData.php');
include(__DIR__.'/../metadata/projects_opportunitiesMetaData.php');



//ACL RELATIONSHIPS
include(__DIR__.'/../metadata/acl_roles_actionsMetaData.php');
include(__DIR__.'/../metadata/acl_roles_usersMetaData.php');
// INBOUND EMAIL
include(__DIR__.'/../metadata/inboundEmail_autoreplyMetaData.php');
include(__DIR__.'/../metadata/inboundEmail_cacheTimestampMetaData.php');
include(__DIR__.'/../metadata/email_cacheMetaData.php');
include(__DIR__.'/../metadata/email_marketing_prospect_listsMetaData.php');
include(__DIR__.'/../metadata/users_signaturesMetaData.php');
//linked documents.
include(__DIR__.'/../metadata/linked_documentsMetaData.php');

// Documents, so we can start replacing Notes as the primary way to attach something to something else.
include(__DIR__.'/../metadata/documents_accountsMetaData.php');
include(__DIR__.'/../metadata/documents_contactsMetaData.php');
include(__DIR__.'/../metadata/documents_opportunitiesMetaData.php');
include(__DIR__.'/../metadata/documents_casesMetaData.php');
include(__DIR__.'/../metadata/documents_bugsMetaData.php');
include(__DIR__.'/../metadata/oauth_nonce.php');
include(__DIR__.'/../metadata/cron_remove_documentsMetaData.php');

//konwledge base
include(__DIR__.'/../metadata/aok_knowledgebase_categoriesMetaData.php');

include(__DIR__.'/../metadata/am_projecttemplates_project_1MetaData.php');
include(__DIR__.'/../metadata/am_projecttemplates_contacts_1MetaData.php');
include(__DIR__.'/../metadata/am_projecttemplates_users_1MetaData.php');

include(__DIR__.'/../metadata/am_tasktemplates_am_projecttemplatesMetaData.php');
include(__DIR__.'/../metadata/aos_contracts_documentsMetaData.php');
include(__DIR__.'/../metadata/aos_quotes_aos_contractsMetaData.php');
include(__DIR__.'/../metadata/aos_quotes_aos_invoicesMetaData.php');
include(__DIR__.'/../metadata/aos_quotes_projectMetaData.php');
include(__DIR__.'/../metadata/aow_processed_aow_actionsMetaData.php');
include(__DIR__.'/../metadata/fp_event_locations_fp_events_1MetaData.php');
include(__DIR__.'/../metadata/fp_events_contactsMetaData.php');
include(__DIR__.'/../metadata/fp_events_fp_event_delegates_1MetaData.php');
include(__DIR__.'/../metadata/fp_events_fp_event_locations_1MetaData.php');
include(__DIR__.'/../metadata/fp_events_leads_1MetaData.php');
include(__DIR__.'/../metadata/fp_events_prospects_1MetaData.php');
include(__DIR__.'/../metadata/jjwg_maps_jjwg_areasMetaData.php');
include(__DIR__.'/../metadata/jjwg_maps_jjwg_markersMetaData.php');
include(__DIR__.'/../metadata/project_contacts_1MetaData.php');
include(__DIR__.'/../metadata/project_users_1MetaData.php');
include(__DIR__.'/../metadata/securitygroups_acl_rolesMetaData.php');
include(__DIR__.'/../metadata/securitygroups_defaultsMetaData.php');
include(__DIR__.'/../metadata/securitygroups_recordsMetaData.php');
include(__DIR__.'/../metadata/securitygroups_usersMetaData.php');
include(__DIR__.'/../metadata/cache_rebuildMetaData.php');

include __DIR__.'/../metadata/surveyquestionoptions_surveyquestionresponsesMetaData.php';

if (file_exists(__DIR__.'/../custom/application/Ext/TableDictionary/tabledictionary.ext.php')) {
    include(__DIR__.'/../custom/application/Ext/TableDictionary/tabledictionary.ext.php');
}
