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


#[\AllowDynamicProperties]
class ACLRole extends SugarBean
{
    public string $module_dir = 'ACLRoles';
    public string $object_name = 'ACLRole';
    public array $relationship_fields = array(
                                    'user_id'=>'users'
                                );

    public function __construct()
    {
        parent::__construct();
        $this->disable_row_level_security = true;
        $this->disable_custom_fields = true;
        $this->setupCustomFields('ACLRole');
        $this->disable_row_level_security = true;
        $this->table_name = 'acl_roles';
        $this->new_schema = true;
    }


    // bug 16790 - missing get_summary_text method led Tracker to display SugarBean's "base implementation"
    public function get_summary_text() : string
    {
        return (string)$this->name;
    }

    /**
     * function setAction($role_id, $action_id, $access)
     *
     * Sets the relationship between a role and an action and sets the access level of that relationship
     *
     * @param string $role_id - the role id GUID
     * @param string $action_id - GUID the ACL Action id
     * @param int $access - the access level ACL_ALLOW_ALL ACL_ALLOW_NONE ACL_ALLOW_OWNER...
     */
    public function setAction(string $role_id, string $action_id, int $access) : void
    {
        $relationship_data = array('role_id'=>$role_id, 'action_id'=>$action_id,);
        $additional_data = array('access_override'=>$access);
        $this->set_relationship('acl_roles_actions', $relationship_data, true, true, $additional_data);
    }


    /**
     * static  getUserRoles($user_id)
     * returns a list of ACLRoles for a given user id
     *
     * @param GUID $user_id
     * @return a list of ACLRole objects
     */
    public function getUserRoles(string $user_id, bool $getAsNameArray = true) : array
    {

        //if we don't have it loaded then lets check against the db
        $additional_where = '';
        $query = 'SELECT acl_roles.* ' .
            'FROM acl_roles ' .
            "INNER JOIN acl_roles_users ON acl_roles_users.user_id = '$user_id' ".
            'AND acl_roles_users.role_id = acl_roles.id AND acl_roles_users.deleted = 0 ' .
            'WHERE acl_roles.deleted=0 ';

        $result = DBManagerFactory::getInstance()->query($query);
        $user_roles = array();

        while ($row = DBManagerFactory::getInstance()->fetchByAssoc($result)) {
            $role = BeanFactory::newBean('ACLRoles');
            $role->populateFromRow($row);
            if ($getAsNameArray) {
                $user_roles[] = $role->name;
            } else {
                $user_roles[] = $role;
            }
        }

        return $user_roles;
    }

    /**
     * static  getUserRoleNames($user_id)
     * returns a list of Role names for a given user id
     *
     * @param string $user_id GUID
     * @return array a list of ACLRole Names
     */
    public static function getUserRoleNames(string $user_id) : array
    {
        $user_roles = sugar_cache_retrieve('RoleMembershipNames_' .$user_id);

        if (!$user_roles) {
            //if we don't have it loaded then lets check against the db
            $additional_where = '';
            $query = 'SELECT acl_roles.* ' .
                'FROM acl_roles ' .
                "INNER JOIN acl_roles_users ON acl_roles_users.user_id = '$user_id' ".
                'AND acl_roles_users.role_id = acl_roles.id AND acl_roles_users.deleted = 0 ' .
                'WHERE acl_roles.deleted=0 ';

            $result = DBManagerFactory::getInstance()->query($query);
            $user_roles = array();

            while ($row = DBManagerFactory::getInstance()->fetchByAssoc($result)) {
                $user_roles[] = $row['name'];
            }

            sugar_cache_put('RoleMembershipNames_' .$user_id, $user_roles);
        }

        return $user_roles;
    }


    /**
     * static getAllRoles($returnAsArray = false)
     *
     * @param bool $returnAsArray - should it return the results as an array of arrays or as an array of ACLRoles
     * @return array either an array of array representations of acl roles or an array of ACLRoles
     */
    public function getAllRoles(bool $returnAsArray = false) : array
    {
        $db = DBManagerFactory::getInstance();
        $query = 'SELECT acl_roles.* FROM acl_roles
                    WHERE acl_roles.deleted=0 ORDER BY name';

        $result = $db->query($query);
        $roles = array();

        while ($row = $db->fetchByAssoc($result)) {
            $role = BeanFactory::newBean('ACLRoles');
            $role->populateFromRow($row);
            if ($returnAsArray) {
                $roles[] = $role->toArray();
            } else {
                $roles[] = $role;
            }
        }
        return $roles;
    }

    /**
     * static getRoleActions($role_id)
     *
     * gets the actions of a given role
     *
     * @param GUID $role_id
     * @return array of actions
     */
    public function getRoleActions(string $role_id, string $type='module') : array
    {
        global $beanList;
        //if we don't have it loaded then lets check against the db
        $additional_where = '';
        $db = DBManagerFactory::getInstance();
        $query = 'SELECT acl_actions.*';
        //only if we have a role id do we need to join the table otherwise lets use the ones defined in acl_actions as the defaults
        if (!empty($role_id)) {
            $query .= ' ,acl_roles_actions.access_override ';
        }
        $query .= ' FROM acl_actions ';

        if (!empty($role_id)) {
            $query .=        " LEFT JOIN acl_roles_actions ON acl_roles_actions.role_id = '$role_id' AND  acl_roles_actions.action_id = acl_actions.id AND acl_roles_actions.deleted = 0";
        }
        $query .= ' WHERE acl_actions.deleted=0 ORDER BY acl_actions.category, acl_actions.name';
        $result = $db->query($query);
        $role_actions = array();

        while ($row = $db->fetchByAssoc($result)) {
            $action = BeanFactory::newBean('ACLActions');
            $action->populateFromRow($row);
            if (!empty($row['access_override'])) {
                $action->aclaccess = $row['access_override'];
            } else {
                $action->aclaccess = ACL_ALLOW_DEFAULT;
            }
            //#27877 . If  there is no this module in beanlist , we will not show them in UI, no matter this module was deleted or not in ACL_ACTIONS table.
            if (empty($beanList[$action->category])) {
                continue;
            }
            //end

            if (!isset($role_actions[$action->category])) {
                $role_actions[$action->category] = array();
            }

            $role_actions[$action->category][$action->acltype][$action->name] = $action->toArray();
        }

        // Sort by translated categories
        uksort($role_actions, 'ACLRole::langCompare');
        return $role_actions;
    }

    private static function langCompare($a, $b)
    {
        global $app_list_strings;
        // Fallback to array key if translation is empty
        $a = empty($app_list_strings['moduleList'][$a]) ? $a : $app_list_strings['moduleList'][$a];
        $b = empty($app_list_strings['moduleList'][$b]) ? $b : $app_list_strings['moduleList'][$b];
        if ($a == $b) {
            return 0;
        }
        return ($a < $b) ? -1 : 1;
    }
    /**
     * function mark_relationships_deleted($id)
     *
     * special case to delete acl_roles_actions relationship
     *
     * @param ACLRole GUID $id
     */
    public function mark_relationships_deleted($id)
    {
        //we need to delete the actions relationship by hand (special case)
        $date_modified = DBManagerFactory::getInstance()->convert("'" . TimeDate::getInstance()->nowDb() . "'",
            'datetime');
        $query =  "UPDATE acl_roles_actions SET deleted=1 , date_modified=$date_modified WHERE role_id = '$id' AND deleted=0";
        $this->db->query($query);
        parent::mark_relationships_deleted($id);
    }

    /**
     *  toArray()
        * returns this role as an array
        *
        * @return array of fields with id, name, description
        */
    public function toArray(bool $dbOnly = false, bool $stringOnly = false, bool $upperKeys = false) : array
    {
        parent::toArray($dbOnly, $stringOnly, $upperKeys);
        $array_fields = array('id', 'name', 'description');
        $arr = array();
        foreach ($array_fields as $field) {
            if (isset($this->$field)) {
                $arr[$field] = $this->$field;
            } else {
                $arr[$field] = '';
            }
        }
        return $arr;
    }
}
