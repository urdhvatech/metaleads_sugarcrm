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
$listViewDefs[$module_name] = [
    'INTERACTIONDATE' => [
        'type' => 'datetimecombo',
        'label' => 'LBL_INTERACTIONDATE',
        'width' => '10%',
        'default' => true,
    ],
    'NAME' => [
        'type' => 'name',
        'link' => true,
        'label' => 'LBL_NAME',
        'width' => '10%',
        'default' => true,
    ],
    'HOSTNAME' => [
        'type' => 'varchar',
        'label' => 'LBL_HOSTNAME',
        'width' => '10%',
        'default' => true,
    ],
    'PATH' => [
        'type' => 'varchar',
        'label' => 'LBL_PATH',
        'width' => '10%',
        'default' => true,
    ],
    'PARAMETERS' => [
        'type' => 'varchar',
        'label' => 'LBL_PARAMETERS',
        'width' => '10%',
        'default' => true,
    ],
    'DURATION' => [
        'type' => 'float',
        'label' => 'LBL_DURATION',
        'width' => '10%',
        'default' => true,
    ],
    'PROTOCOL' => [
        'type' => 'varchar',
        'label' => 'LBL_PROTOCOL',
        'width' => '10%',
        'default' => true,
    ],
];
