<?php
/**
 * This file is part of the "Meta Leads" package.
 *
 * @package Meta Leads
 * @author Urdhva Tech <contact@urdhva-tech.com>
 * @link https://www.urdhva-tech.com
 * @copyright Urdhva Tech
 * @license As specified in the License Agreement supplied with this package.
 */

if (!defined('sugarEntry') || !sugarEntry) {
    die('Not A Valid Entry Point');
}

$module_name = 'ut_MetaLeadSubmissions';
$subpanel_layout = array(
    'top_buttons' => array(),
    'where' => '',
    'list_fields' => array(
        'name' => array(
            'vname' => 'LBL_NAME',
            'widget_class' => 'SubPanelDetailViewLink',
            'width' => '20%',
        ),
        'meta_leadgen_id' => array(
            'vname' => 'LBL_META_LEADGEN_ID',
            'width' => '15%',
        ),
        'form_name' => array(
            'vname' => 'LBL_FORM_NAME',
            'width' => '18%',
        ),
        'page_name' => array(
            'vname' => 'LBL_PAGE_NAME',
            'width' => '15%',
        ),
        'platform' => array(
            'vname' => 'LBL_PLATFORM',
            'width' => '10%',
        ),
        'submitted_at' => array(
            'vname' => 'LBL_SUBMITTED_AT',
            'width' => '15%',
        ),
    ),
);
