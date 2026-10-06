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

$viewdefs['sf_Dialogs']['base']['view']['record'] = [
    'panels' => [
        [
            'name' => 'panel_header',
            'label' => 'LBL_RECORD_HEADER',
            'header' => true,
            'fields' => [
                [
                    'name' => 'picture',
                    'type' => 'avatar',
                    'width' => 32,
                    'height' => 32,
                    'size' => 'small',
                    'dismiss_label' => true,
                    'readonly' => true,
                ],
                'name',
                [
                    'name' => 'favorite',
                    'label' => 'LBL_FAVORITE',
                    'type' => 'favorite',
                    'readonly' => true,
                    'dismiss_label' => true,
                ],
                [
                    'name' => 'follow',
                    'label' => 'LBL_FOLLOW',
                    'type' => 'follow',
                    'readonly' => true,
                    'dismiss_label' => true,
                ],
            ],
        ],
        [
            'name' => 'panel_body',
            'label' => 'LBL_RECORD_BODY',
            'columns' => 2,
            'labelsOnTop' => true,
            'placeholders' => true,
            'fields' => [
                [
                    'name' => null,
                    'span' => 6,
                ],
                [
                    'span' => 6,
                ],
                [
                    'span' => 6,
                ],
                [
                    'span' => 6,
                ],
                [
                    'name' => 'sf_dialogs_contacts_name',
                ],
                [
                    'name' => 'sf_dialogs_leads_name',
                ],
                [
                    'name' => 'dialog_id',
                    'label' => 'LBL_DIALOG_ID',
                ],
                [
                    'name' => 'start_form_id',
                    'label' => 'LBL_START_FORM_ID',
                ],
                [
                    'name' => 'completed_date',
                    'label' => 'LBL_COMPLETED_DATE',
                    'span' => 6,
                ],
                [
                    'span' => 6,
                ],
                [
                    'name' => 'dialog_response',
                    'studio' => 'visible',
                    'label' => 'LBL_DIALOG_RESPONSE',
                    'span' => 6,
                ],
                [
                    'span' => 6,
                ],
                [
                    'name' => 'description',
                    'comment' => 'Full text of the note',
                    'label' => 'LBL_DESCRIPTION',
                ],
            ],
        ],
    ],
    'templateMeta' => [
        'useTabs' => false,
        'tabDefs' => [
            'LBL_RECORD_BODY' => [
                'newTab' => false,
                'panelDefault' => 'expanded',
            ],
        ],
    ],
];
