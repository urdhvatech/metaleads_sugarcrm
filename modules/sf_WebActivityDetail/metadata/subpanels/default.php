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

$module_name = 'sf_WebActivityDetail';
$subpanel_layout = [
    'top_buttons' => [],
    'where' => '',
    'list_fields' => [
        'interactiondate' => [
            'type' => 'datetimecombo',
            'vname' => 'LBL_INTERACTIONDATE',
            'width' => '10%',
            'default' => true,
        ],
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
        'hostname' => [
            'type' => 'varchar',
            'vname' => 'LBL_HOSTNAME',
            'width' => '10%',
            'default' => true,
        ],
        'path' => [
            'type' => 'varchar',
            'vname' => 'LBL_PATH',
            'width' => '10%',
            'default' => true,
        ],
        'parameters' => [
            'type' => 'varchar',
            'vname' => 'LBL_PARAMETERS',
            'width' => '10%',
            'default' => true,
        ],
        'duration' => [
            'type' => 'float',
            'vname' => 'LBL_DURATION',
            'width' => '10%',
            'default' => true,
        ],
        'protocol' => [
            'type' => 'varchar',
            'vname' => 'LBL_PROTOCOL',
            'width' => '10%',
            'default' => true,
        ],
    ],
];
