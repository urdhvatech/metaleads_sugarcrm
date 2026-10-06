<?php
/*
 * Your installation or use of this SugarCRM file is subject to the applicable
 * terms available at
 * http://support.sugarcrm.com/Resources/Master_Subscription_Agreements/.
 * If you do not agree to all of the applicable terms or do not have the
 * authority to bind the entity as an authorized representative, then do not
 * install or use this SugarCRM file.
 *
 * Copyright (C) SugarCRM Inc. All rights reserved.
 */


use Sugarcrm\Sugarcrm\CSP\Nonce;

class SugarWidgetSubPanelRemoveButtonMeetings extends SugarWidgetField
{
    public function displayHeaderCell($layout_def)
    {
        return '&nbsp;';
    }

    public function displayList($layout_def)
    {
        global $app_strings;


        $parent_record_id = $_REQUEST['record'];
        $parent_module = $_REQUEST['module'];

        $action = 'DeleteRelationship';
        $record = $layout_def['fields']['ID'];

        $return_module = $_REQUEST['module'];
        $return_action = 'SubPanelViewer';
        $subpanel = $layout_def['subpanel_id'];
        $return_id = $_REQUEST['record'];


        if (isset($GLOBALS['FOCUS'])) {
            $focus = $GLOBALS['FOCUS'];
        } /* Handle case where we generate subpanels from MySettings/LoadTabSubpanels.php */
        elseif ($return_module == 'MySettings') {
            $focus = BeanFactory::getBean($_REQUEST['loadModule'], $return_id);
        }

        //CCL - Comment out restriction to not remove assigned user
        //if($focus->assigned_user_id == $record) return '';

        if (isset($layout_def['linked_field_set']) && !empty($layout_def['linked_field_set'])) {
            $linked_field = $layout_def['linked_field_set'];
        } else {
            $linked_field = $layout_def['linked_field'];
        }
        $refresh_page = 0;
        if (!empty($layout_def['refresh_page'])) {
            $refresh_page = 1;
        }
        $return_url = "index.php?module=$return_module&action=$return_action&subpanel=$subpanel&record=$return_id&sugar_body_only=1";

        $icon_remove_text = strtolower($app_strings['LBL_ID_FF_REMOVE']);
        $remove_url = $layout_def['start_link_wrapper']
            . "index.php?module=$parent_module"
            . "&action=$action"
            . "&record=$parent_record_id"
            . "&linked_field=$linked_field"
            . "&linked_id=$record"
            . '&return_url=' . urlencode(urlencode($return_url))
            . "&refresh_page=$refresh_page"
            . $layout_def['end_link_wrapper'];
        $remove_confirmation_text = $app_strings['NTC_REMOVE_CONFIRMATION'];
        //based on listview since that lets you select records
        if ($layout_def['ListView']) {
            $nonce = Nonce::create();
            return '<a href="#"'
            . ' class="listViewTdToolsS1"'
            . ' data-onclick-' . htmlspecialchars($nonce, ENT_QUOTES) . '="return sp_rem_conf() && sub_p_rem(\''
            . htmlspecialchars($subpanel, ENT_QUOTES) . '\', \''
            . htmlspecialchars($linked_field, ENT_QUOTES) . '\', \''
            . htmlspecialchars($record, ENT_QUOTES) . '\', '
            . htmlspecialchars($refresh_page, ENT_QUOTES) . ');"'
            . '>' . htmlspecialchars($icon_remove_text, ENT_QUOTES) . '</a>';
        } else {
            return '';
        }
    }
}
