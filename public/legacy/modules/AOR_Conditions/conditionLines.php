<?php
/**
 * Advanced OpenReports, SugarCRM Reporting.
 * @package Advanced OpenReports for SugarCRM
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


function display_condition_lines($focus, $field, $value, $view)
{
    global $locale, $app_list_strings, $mod_strings;

    $html = '';

    if (!is_file('cache/jsLanguage/AOR_Conditions/' . $GLOBALS['current_language'] . '.js')) {
        require_once('include/language/jsLanguage.php');
        jsLanguage::createModuleStringsCache('AOR_Conditions', $GLOBALS['current_language']);
    }
    $html .= '<script src="cache/jsLanguage/AOR_Conditions/'. $GLOBALS['current_language'] . '.js"></script>';

    if ($view === 'EditView') {
        $html .= '<script src="modules/AOR_Conditions/conditionLines.js"></script>';
        $html .= "<table border='0' cellspacing='4' width='100%' id='aor_conditionLines'></table>";

        $html .= "<div style='padding-top: 10px; padding-bottom:10px;'>";
        $html .= "<input type=\"button\" tabindex=\"116\" class=\"button\" value=\"".$mod_strings['LBL_ADD_CONDITION']."\" id=\"btn_ConditionLine\" onclick=\"insertConditionLine()\" disabled/>";
        $html .= '</div>';


        if (isset($focus->report_module) && $focus->report_module != '') {
            require_once('modules/AOW_WorkFlow/aow_utils.php');
            $html .= '<script>';
            $html .= "report_module = \"".$focus->report_module."\";";
            $html .= "document.getElementById('btn_ConditionLine').disabled = '';";
            if ($focus->id != '') {
                $sql = "SELECT id FROM aor_conditions WHERE aor_report_id = '".$focus->id."' AND deleted = 0 ORDER BY condition_order ASC";
                $result = $focus->db->query($sql);

                while ($row = $focus->db->fetchByAssoc($result)) {
                    $condition_name = BeanFactory::newBean('AOR_Conditions');
                    $condition_name->retrieve($row['id']);
                    $condition_name->module_path = unserialize(base64_decode($condition_name->module_path));
                    $html .= "report_fields = \"".trim(preg_replace('/\s+/', ' ', (string) getModuleFields(getRelatedModule($focus->report_module, $condition_name->module_path[0]))))."\";";
                    if ($condition_name->value_type === 'Date') {
                        $condition_name->value = unserialize(base64_decode($condition_name->value));
                    }
                    $condition_item = json_encode($condition_name->toArray());
                    $html .= 'loadConditionLine(' .$condition_item. ');';
                }
            }
            $html .= "report_fields = \"".trim(preg_replace('/\s+/', ' ', (string) getModuleFields($focus->report_module)))."\";";
            $html .= '</script>';
        }
    } elseif ($view === 'DetailView') {
        $html .= '<script src="modules/AOR_Conditions/conditionLines.js"></script>';
        $html .= "<table border='0' cellspacing='0' width='100%' id='aor_conditionLines'></table>";


        if (isset($focus->report_module) && $focus->report_module != '') {
            require_once('modules/AOW_WorkFlow/aow_utils.php');
            $html .= '<script>';
            $html .= "report_fields = \"".trim(preg_replace('/\s+/', ' ', (string) getModuleFields($focus->report_module)))."\";";
            $html .= "report_module = \"".$focus->report_module."\";";
            $sql = "SELECT id FROM aor_conditions WHERE aor_report_id = '".$focus->id."' AND deleted = 0 ORDER BY condition_order ASC";
            $result = $focus->db->query($sql);

            while ($row = $focus->db->fetchByAssoc($result)) {
                $condition_name = BeanFactory::newBean('AOR_Conditions');
                $condition_name->retrieve($row['id']);
                $condition_name->module_path = unserialize(base64_decode($condition_name->module_path));
                if ($condition_name->value_type === 'Date') {
                    $condition_name->value = unserialize(base64_decode($condition_name->value));
                }
                $condition_item = json_encode($condition_name->toArray());
                $html .= 'loadConditionLine(' .$condition_item. ');';
            }
            $html .= '</script>';
        }
    }
    return $html;
}
