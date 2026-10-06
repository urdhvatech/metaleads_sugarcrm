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

$dictionary['sf_webactivity_accounts'] = [
   'table' => 'sf_webactivity_accounts',
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
        'sf_webactivity_accountsaccounts_ida' => [
            'name' => 'sf_webactivity_accountsaccounts_ida',
            'type' => 'varchar',
            'len' => 36,
        ],
        'sf_webactivity_accountssf_webactivity_idb' => [
            'name' => 'sf_webactivity_accountssf_webactivity_idb',
            'type' => 'varchar',
            'len' => 36,
        ],
    ],
    'indices' => [
        [
            'name' => 'sf_webactivity_accountsspk',
            'type' => 'primary',
            'fields' => [
                'id',
            ],
        ],
        [
            'name' => 'sf_webactivity_accounts_ida1',
            'type' => 'index',
            'fields' => [
                'sf_webactivity_accountsaccounts_ida',
            ],
        ],
        [
            'name' => 'sf_webactivity_accounts_alt',
            'type' => 'alternate_key',
            'fields' => [
                'sf_webactivity_accountssf_webactivity_idb',
            ],
        ],
    ],
    'relationships' => [
        'sf_webactivity_accounts' => [
            'lhs_module' => 'Accounts',
            'lhs_table' => 'accounts',
            'lhs_key' => 'id',
            'rhs_module' => 'sf_webActivity',
            'rhs_table' => 'sf_webactivity',
            'rhs_key' => 'id',
            'relationship_type' => 'one-to-many',
            'join_table' => 'sf_webactivity_accounts',
            'join_key_lhs' => 'sf_webactivity_accountsaccounts_ida',
            'join_key_rhs' => 'sf_webactivity_accountssf_webactivity_idb',
            'true_relationship_type' => 'one-to-many',
        ],
    ],
];
