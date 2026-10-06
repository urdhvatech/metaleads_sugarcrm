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
$viewdefs['Opportunities']['base']['layout']['subpanel'] = [
    'template' => 'panel',
    'components' => [
        [
            'view' => 'panel-top',
        ],
        [
            'view' => [
                'name' => 'record-snapshot-selector',
                'css_class' => 'pl-2 pb-1 flex',
                'cssChild' => 'table-cell',
                'layoutType' => 'subpanel',
            ],
        ],
        [
            'view' => 'subpanel-list',
        ],
        [
            'view' => 'list-pagination',
        ],
    ],
    'last_state' => [
        'id' => 'subpanel',
    ],
];
