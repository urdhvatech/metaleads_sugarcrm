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
$dictionary['accounts_summz_gai'] = [
    'true_relationship_type' => 'one-to-many',
    'from_studio' => false,
    'relationships' => [
        'accounts_summz_gai' => [
            'lhs_module' => 'Accounts',
            'lhs_table' => 'accounts',
            'lhs_key' => 'id',
            'rhs_module' => 'SummarizationGai',
            'rhs_table' => 'summarization_gai',
            'rhs_key' => 'id',
            'relationship_type' => 'many-to-many',
            'join_table' => 'accounts_summz_gai_rel',
            'join_key_lhs' => 'accounts_summz_gaiaccounts_ida',
            'join_key_rhs' => 'accounts_summz_gaisummarization_gai_idb',
        ],
    ],
    'table' => 'accounts_summz_gai_rel',
    'fields' => [
        'id' => [
            'name' => 'id',
            'type' => 'id',
        ],
        'date_modified' => [
            'name' => 'date_modified',
            'type' => 'datetime',
        ],
        'deleted' => [
            'name' => 'deleted',
            'type' => 'bool',
            'default' => 0,
        ],
        'accounts_summz_gaiaccounts_ida' => [
            'name' => 'accounts_summz_gaiaccounts_ida',
            'type' => 'id',
        ],
        'accounts_summz_gaisummarization_gai_idb' => [
            'name' => 'accounts_summz_gaisummarization_gai_idb',
            'type' => 'id',
        ],
    ],
    'indices' =>[
        [
            'name' => 'idx_accounts_sumz_gai_1_pk',
            'type' => 'primary',
            'fields' => ['id'],
        ],
        [
            'name' => 'idx_accounts_summz_gai_ida_accounts_deleted',
            'type' => 'index',
            'fields' => ['accounts_summz_gaiaccounts_ida', 'deleted'],
        ],
        [
            'name' => 'idx_accounts_summz_gai_idb_gai_deleted',
            'type' => 'index',
            'fields' => ['accounts_summz_gaisummarization_gai_idb', 'deleted'],
        ],
        [
            'name' => 'accounts_summz_gai_idb_gai_alt',
            'type' => 'alternate_key',
            'fields' => ['accounts_summz_gaisummarization_gai_idb'],
        ],
    ],
];
