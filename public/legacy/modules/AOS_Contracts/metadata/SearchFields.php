<?php
if (!defined('sugarEntry') || !sugarEntry) {
    die('Not A Valid Entry Point');
}
/**
 * Products, Quotations & Invoices modules.
 * Extensions to SugarCRM
 * @package Advanced OpenSales for SugarCRM
 * @subpackage Products
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
 * @author SalesAgility Ltd <support@salesagility.com>
 */
$searchFields['AOS_Contracts'] =
    array(
        'name' => array( 'query_type'=>'default'),
        'current_user_only'=> array('query_type'=>'default','db_field'=>array('assigned_user_id'),'my_items'=>true, 'vname' => 'LBL_CURRENT_USER_FILTER', 'type' => 'bool'),
        'assigned_user_id'=> array('query_type'=>'default'),
        'favorites_only' => array(
            'query_type'=>'format',
            'operator' => 'subquery',
            'checked_only' => true,
            'subquery' => "SELECT favorites.parent_id FROM favorites
                                WHERE favorites.deleted = 0
                                    and favorites.parent_type = 'AOS_Contracts'
                                    and favorites.assigned_user_id = '{1}'",
            'db_field'=>array('id')),
        //Range Search Support
       'range_end_date' => array('query_type' => 'default', 'enable_range_search' => true, 'is_date_field' => true),
       'start_range_end_date' => array('query_type' => 'default', 'enable_range_search' => true, 'is_date_field' => true),
       'end_range_end_date' => array('query_type' => 'default', 'enable_range_search' => true, 'is_date_field' => true),
       'range_total_contract_value' => array('query_type' => 'default', 'enable_range_search' => true, 'is_date_field' => true),
       'start_range_total_contract_value' => array('query_type' => 'default', 'enable_range_search' => true, 'is_date_field' => true),
       'end_range_total_contract_value' => array('query_type' => 'default', 'enable_range_search' => true, 'is_date_field' => true),

    );
