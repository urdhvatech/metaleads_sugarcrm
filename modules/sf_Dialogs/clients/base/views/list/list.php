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

$module_name = 'sf_Dialogs';
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
                    'name' => 'dialog_id',
                    'label' => 'LBL_DIALOG_ID',
                    'enabled' => true,
                    'width' => '10%',
                    'default' => true,
                ],
                [
                    'name' => 'start_form_id',
                    'label' => 'LBL_START_FORM_ID',
                    'enabled' => true,
                    'width' => '10%',
                    'default' => true,
                ],
                [
                    'name' => 'completed_date',
                    'label' => 'LBL_COMPLETED_DATE',
                    'enabled' => true,
                    'width' => '10%',
                    'default' => true,
                ],
                [
                    'name' => 'assigned_user_name',
                    'label' => 'LBL_ASSIGNED_TO_NAME',
                    'width' => '9%',
                    'default' => true,
                    'enabled' => true,
                    'link' => true,
                ],
            ],
        ],
    ],
    'orderBy' => [
        'field' => 'date_modified',
        'direction' => 'desc',
    ],
];
