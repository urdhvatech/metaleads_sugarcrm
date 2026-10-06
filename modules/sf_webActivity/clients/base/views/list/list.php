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
$viewdefs[$module_name]['base']['view']['list'] = [
    'panels' => [
        [
            'label' => 'LBL_PANEL_1',
            'fields' => [
                [
                    'name' => 'name',
                    'label' => 'LBL_NAME',
                    'default' => true,
                    'enabled' => true,
                    'link' => true,
                    'width' => '10%',
                ],
                [
                    'name' => 'startdate',
                    'label' => 'LBL_STARTDATE',
                    'enabled' => true,
                    'width' => '10%',
                    'default' => true,
                ],
                [
                    'name' => 'referrerkeywords',
                    'label' => 'LBL_REFERRERKEYWORDS',
                    'enabled' => true,
                    'sortable' => false,
                    'width' => '10%',
                    'default' => true,
                ],
                [
                    'name' => 'touchpoint',
                    'label' => 'LBL_TOUCHPOINT',
                    'enabled' => true,
                    'width' => '10%',
                    'default' => true,
                ],
                [
                    'name' => 'city',
                    'label' => 'LBL_CITY',
                    'enabled' => true,
                    'width' => '10%',
                    'default' => true,
                ],
                [
                    'name' => 'region',
                    'label' => 'LBL_REGION',
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
