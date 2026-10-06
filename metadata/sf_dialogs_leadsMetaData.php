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

$dictionary['sf_dialogs_leads'] = [
    'table' => 'sf_dialogs_leads',
    'fields' => [
        'id' => [
            'name' => 'id',
            'type' => 'varchar',
            'len' => 36,
        ],
        'date_modified' => [
            'name' => 'date_modified',
            'type' => 'datetime',
        ],
        'deleted' => [
            'name' => 'deleted',
            'type' => 'bool',
            'len' => '1',
            'default' => '0',
            'required' => true,
        ],
        'sf_dialogs_leadsleads_ida' => [
            'name' => 'sf_dialogs_leadsleads_ida',
            'type' => 'varchar',
            'len' => 36,
        ],
        'sf_dialogs_leadssf_dialogs_idb' => [
            'name' => 'sf_dialogs_leadssf_dialogs_idb',
            'type' => 'varchar',
            'len' => 36,
        ],
    ],
    'indices' => [
        [
            'name' => 'sf_dialogs_leadsspk',
            'type' => 'primary',
            'fields' => [
                'id',
            ],
        ],
        [
            'name' => 'sf_dialogs_leads_ida1',
            'type' => 'index',
            'fields' => [
                'sf_dialogs_leadsleads_ida',
            ],
        ],
        [
            'name' => 'sf_dialogs_leads_alt',
            'type' => 'alternate_key',
            'fields' => [
                'sf_dialogs_leadssf_dialogs_idb',
            ],
        ],
    ],
    'relationships' => [
        'sf_dialogs_leads' => [
            'lhs_module' => 'Leads',
            'lhs_table' => 'leads',
            'lhs_key' => 'id',
            'rhs_module' => 'sf_Dialogs',
            'rhs_table' => 'sf_dialogs',
            'rhs_key' => 'id',
            'relationship_type' => 'one-to-many',
            'join_table' => 'sf_dialogs_leads',
            'join_key_lhs' => 'sf_dialogs_leadsleads_ida',
            'join_key_rhs' => 'sf_dialogs_leadssf_dialogs_idb',
            'true_relationship_type' => 'one-to-many',
        ],
    ],
];
