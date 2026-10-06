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
$dictionary['cases_summz_gai'] = [
    'true_relationship_type' => 'one-to-many',
    'from_studio' => false,
    'relationships' => [
        'cases_summz_gai' => [
            'lhs_module' => 'Cases',
            'lhs_table' => 'cases',
            'lhs_key' => 'id',
            'rhs_module' => 'SummarizationGai',
            'rhs_table' => 'summarization_gai',
            'rhs_key' => 'id',
            'relationship_type' => 'many-to-many',
            'join_table' => 'cases_summz_gai_rel',
            'join_key_lhs' => 'cases_summz_gaicases_ida',
            'join_key_rhs' => 'cases_summz_gaisummarization_gai_idb',
        ],
    ],
    'table' => 'cases_summz_gai_rel',
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
        'cases_summz_gaicases_ida' => [
            'name' => 'cases_summz_gaicases_ida',
            'type' => 'id',
        ],
        'cases_summz_gaisummarization_gai_idb' => [
            'name' => 'cases_summz_gaisummarization_gai_idb',
            'type' => 'id',
        ],
    ],
    'indices' =>[
        [
            'name' => 'idx_cases_sumz_gai_1_pk',
            'type' => 'primary',
            'fields' => ['id'],
        ],
        [
            'name' => 'idx_cases_summz_gai_ida_cases_deleted',
            'type' => 'index',
            'fields' => ['cases_summz_gaicases_ida', 'deleted'],
        ],
        [
            'name' => 'idx_cases_summz_gai_idb_gai_deleted',
            'type' => 'index',
            'fields' => ['cases_summz_gaisummarization_gai_idb', 'deleted'],
        ],
        [
            'name' => 'cases_summz_gai_idb_gai_alt',
            'type' => 'alternate_key',
            'fields' => ['cases_summz_gaisummarization_gai_idb'],
        ],
    ],
];
