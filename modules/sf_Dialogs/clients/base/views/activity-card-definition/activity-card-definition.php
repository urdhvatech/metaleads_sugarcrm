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

$viewdefs['sf_Dialogs']['base']['view']['activity-card-definition'] = [
    'record_date' => 'completed_date',
    'fields' => [
        'name',
        'duration',
        'dialog_response',
        'completed_date',
    ],
    'card_menu' => [
        [
            'type' => 'focuscab',
            'css_class' => 'dashboard-icon',
            'icon' => 'sicon-focus-drawer',
            'tooltip' => 'LBL_FOCUS_DRAWER_DASHBOARD',
        ],
    ],
];
