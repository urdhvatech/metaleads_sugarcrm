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
$viewdefs [$module_name]['QuickCreate'] = [
    'templateMeta' => [
        'maxColumns' => '2',
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
    ],
    'panels' => [
        'default' => [
            [
                'name',
                'assigned_user_name',
            ],
            [
                [
                    'name' => 'sf_dialogs_contacts_name',
                    'label' => 'LBL_SF_DIALOGS_CONTACTS_FROM_CONTACTS_TITLE',
                ],
                [
                    'name' => 'sf_dialogs_leads_name',
                    'label' => 'LBL_SF_DIALOGS_LEADS_FROM_LEADS_TITLE',
                ],
            ],
            [
                [
                    'name' => 'dialog_id',
                    'label' => 'LBL_DIALOG_ID',
                ],
                [
                    'name' => 'start_form_id',
                    'label' => 'LBL_START_FORM_ID',
                ],
            ],
            [
                [
                    'name' => 'completed_date',
                    'label' => 'LBL_COMPLETED_DATE',
                ],
                '',
            ],
            [
                [
                    'name' => 'dialog_response',
                    'studio' => 'visible',
                    'label' => 'LBL_DIALOG_RESPONSE',
                ],
            ],
            [
                'description',
            ],
        ],
    ],
];
