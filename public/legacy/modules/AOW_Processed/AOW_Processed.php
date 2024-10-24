<?php
/**
 * Advanced OpenWorkflow, Automating SugarCRM.
 * @package Advanced OpenWorkflow for SugarCRM
 * @copyright SalesAgility Ltd http://www.salesagility.com
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU AFFERO GENERAL PUBLIC LICENSE as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU AFFERO GENERAL PUBLIC LICENSE
 * along with this program; if not, see http://www.gnu.org/licenses
 * or write to the Free Software Foundation,Inc., 51 Franklin Street,
 * Fifth Floor, Boston, MA 02110-1301  USA
 *
 * @author SalesAgility <info@salesagility.com>
 */


#[\AllowDynamicProperties]
class AOW_Processed extends Basic
{
    public bool $new_schema = true;
    public string $module_dir = 'AOW_Processed';
    public string $object_name = 'AOW_Processed';
    public string $table_name = 'aow_processed';
    public bool $importable = false;
    public $disable_row_level_security = true ;

    public string $id;
    public string $name;
    public string $date_entered;
    public string $date_modified;
    public string $modified_user_id;
    public string $modified_by_name;
    public string $created_by;
    public string $created_by_name;
    public string $description;
    public int $deleted;
    public $created_by_link;
    public $modified_user_link;
    public $aow_workflow_id;
    public $aow_workflow;
    public $aow_action_id;
    public $aow_action;
    public $parent_id;
    public $parent_type;
    public $status;

    public function __construct()
    {
        parent::__construct();
    }




    public function bean_implements($interface) : bool
    {
        switch ($interface) {
            case 'ACL': return true;
        }
        return false;
    }
}
