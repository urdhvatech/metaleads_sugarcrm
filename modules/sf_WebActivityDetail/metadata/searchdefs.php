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
$searchdefs[$module_name] = [
    'layout' => [
        'basic_search' => [
            'title' => [
                'type' => 'varchar',
                'label' => 'LBL_TITLE',
                'width' => '10%',
                'default' => true,
                'name' => 'title',
            ],
            'webinteractionid' => [
                'type' => 'varchar',
                'label' => 'LBL_WEBINTERACTIONID',
                'width' => '10%',
                'default' => true,
                'name' => 'webinteractionid',
            ],
            'websessionid' => [
                'type' => 'varchar',
                'label' => 'LBL_WEBSESSIONID',
                'width' => '10%',
                'default' => true,
                'name' => 'websessionid',
            ],
            'current_user_only' => [
                'name' => 'current_user_only',
                'label' => 'LBL_CURRENT_USER_FILTER',
                'type' => 'bool',
                'default' => true,
                'width' => '10%',
            ],
            'favorites_only' => [
                'name' => 'favorites_only',
                'label' => 'LBL_FAVORITES_FILTER',
                'type' => 'bool',
                'default' => true,
                'width' => '10%',
            ],
        ],
        'advanced_search' => [
            'name',
            [
                'name' => 'assigned_user_id',
                'label' => 'LBL_ASSIGNED_TO',
                'type' => 'enum',
                'function' => [
                    'name' => 'get_user_array',
                    'params' => [false],
                ],
            ],
            [
                'name' => 'favorites_only',
                'label' => 'LBL_FAVORITES_FILTER',
                'type' => 'bool',
            ],
        ],
    ],
    'templateMeta' => [
        'maxColumns' => '3',
        'maxColumnsBasic' => '4',
        'widths' => [
            'label' => '10',
            'field' => '30',
        ],
    ],
];
