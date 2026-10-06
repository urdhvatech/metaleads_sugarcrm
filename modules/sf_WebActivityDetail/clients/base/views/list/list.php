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
$viewdefs[$module_name]['base']['view']['list'] = [
    'panels' => [
        [
            'label' => 'LBL_PANEL_1',
            'fields' => [
                [
                    'name' => 'interactiondate',
                    'label' => 'LBL_INTERACTIONDATE',
                    'enabled' => true,
                    'width' => '10%',
                    'default' => true,
                ],
                [
                    'name' => 'name',
                    'label' => 'LBL_NAME',
                    'default' => true,
                    'enabled' => true,
                    'link' => true,
                    'width' => '10%',
                ],
                [
                    'name' => 'hostname',
                    'label' => 'LBL_HOSTNAME',
                    'enabled' => true,
                    'width' => '10%',
                    'default' => true,
                ],
                [
                    'name' => 'path',
                    'label' => 'LBL_PATH',
                    'enabled' => true,
                    'width' => '10%',
                    'default' => true,
                ],
                [
                    'name' => 'parameters',
                    'label' => 'LBL_PARAMETERS',
                    'enabled' => true,
                    'width' => '10%',
                    'default' => true,
                ],
                [
                    'name' => 'duration',
                    'label' => 'LBL_DURATION',
                    'enabled' => true,
                    'width' => '10%',
                    'default' => true,
                ],
                [
                    'name' => 'protocol',
                    'label' => 'LBL_PROTOCOL',
                    'enabled' => true,
                    'width' => '10%',
                    'default' => true,
                ],
            ],
        ],
    ],
    'orderBy' =>
        [
            'field' => 'date_modified',
            'direction' => 'desc',
        ],
];
