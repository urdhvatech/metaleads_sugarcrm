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

$module_name = 'sf_webActivity';
$subpanel_layout = [
    'top_buttons' => [],
    'where' => '',
    'list_fields' => [
        'name' => [
            'type' => 'name',
            'link' => true,
            'vname' => 'LBL_NAME',
            'width' => '10%',
            'default' => true,
            'widget_class' => 'SubPanelDetailViewLink',
            'target_module' => null,
            'target_record_key' => null,
        ],
        'startdate' => [
            'type' => 'datetimecombo',
            'vname' => 'LBL_STARTDATE',
            'width' => '10%',
            'default' => true,
        ],
        'referrerkeywords' => [
            'type' => 'text',
            'studio' => 'visible',
            'vname' => 'LBL_REFERRERKEYWORDS',
            'sortable' => false,
            'width' => '10%',
            'default' => true,
        ],
        'touchpoint' => [
            'type' => 'varchar',
            'vname' => 'LBL_TOUCHPOINT',
            'width' => '10%',
            'default' => true,
        ],
        'city' => [
            'type' => 'varchar',
            'vname' => 'LBL_CITY',
            'width' => '10%',
            'default' => true,
        ],
        'region' => [
            'type' => 'varchar',
            'vname' => 'LBL_REGION',
            'width' => '10%',
            'default' => true,
        ],
        'edit_button' => [
            'widget_class' => 'SubPanelEditButton',
            'module' => 'sf_webActivity',
            'width' => '4%',
            'default' => true,
        ],
        'remove_button' => [
            'widget_class' => 'SubPanelRemoveButton',
            'module' => 'sf_webActivity',
            'width' => '5%',
            'default' => true,
        ],
    ],
];
