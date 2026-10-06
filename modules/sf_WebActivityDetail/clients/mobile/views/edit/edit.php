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
$viewdefs[$module_name]['mobile']['view']['edit'] = [
    'templateMeta' => [
        'maxColumns' => '1',
        'widths' => [
            [
                'label' => '10',
                'field' => '30',
            ],
            [
                'label' => '10',
                'field' => '30',
            ],
        ],
        'useTabs' => false,
        'tabDefs' => [
            'LBL_PANEL_DEFAULT' => [
                'newTab' => false,
                'panelDefault' => 'expanded',
            ],
        ],
    ],
    'panels' => [
        [
            'label' => 'LBL_PANEL_DEFAULT',
            'fields' => [
                [
                    'name' => 'title',
                    'label' => 'LBL_TITLE',
                ],
                [
                    'name' => 'duration',
                    'label' => 'LBL_DURATION',
                ],
                [
                    'name' => 'hostname',
                    'label' => 'LBL_HOSTNAME',
                ],
                [
                    'name' => 'interactiondate',
                    'label' => 'LBL_INTERACTIONDATE',
                ],
                [
                    'name' => 'path',
                    'label' => 'LBL_PATH',
                ],
                [
                    'name' => 'parameters',
                    'label' => 'LBL_PARAMETERS',
                ],
                [
                    'name' => 'sf_web_activity_sf_web_activity_detail_name',
                    'label' => 'LBL_SF_WEB_ACTIVITY_SF_WEB_ACTIVITY_DETAIL_FROM_SF_WEB_ACTIVITY_TITLE',
                ],
            ],
        ],
    ],
];
