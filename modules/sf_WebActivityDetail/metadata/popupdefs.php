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

$popupMeta = [
    'moduleMain' => 'sf_WebActivityDetail',
    'varName' => 'sf_WebActivityDetail',
    'orderBy' => 'sf_webactivitydetail.name',
    'whereClauses' => [
        'name' => 'sf_webactivitydetail.name',
    ],
    'searchInputs' => [
        'sf_webactivitydetail_number',
        'name',
        'priority',
        'status',
    ],
    'listviewdefs' => [
        'INTERACTIONDATE' => [
            'type' => 'datetimecombo',
            'label' => 'LBL_INTERACTIONDATE',
            'width' => '10%',
            'default' => true,
        ],
        'TITLE' => [
            'type' => 'varchar',
            'label' => 'LBL_TITLE',
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
    ],
];
