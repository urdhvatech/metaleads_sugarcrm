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

$dictionary['sf_webactivitydetail_sf_webactivity'] = [
    'table' => 'sf_webactivitydetail_sf_webactivity',
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
        'sf_webactivitydetail_sf_webactivitysf_webactivity_ida' => [
            'name' => 'sf_webactivitydetail_sf_webactivitysf_webactivity_ida',
            'type' => 'varchar',
            'len' => 36,
        ],
        'sf_webactivitydetail_sf_webactivitysf_webactivitydetail_idb' => [
            'name' => 'sf_webactivitydetail_sf_webactivitysf_webactivitydetail_idb',
            'type' => 'varchar',
            'len' => 36,
        ],
    ],
    'indices' => [
        [
            'name' => 'sf_webactivitydetail_sf_webactivityspk',
            'type' => 'primary',
            'fields' => [
                'id',
            ],
        ],
        [
            'name' => 'sf_webactivitydetail_sf_webactivity_ida1',
            'type' => 'index',
            'fields' => [
                'sf_webactivitydetail_sf_webactivitysf_webactivity_ida',
            ],
        ],
        [
            'name' => 'sf_webactivitydetail_sf_webactivity_alt',
            'type' => 'alternate_key',
            'fields' => [
                'sf_webactivitydetail_sf_webactivitysf_webactivitydetail_idb',
            ],
        ],
    ],
    'relationships' => [
        'sf_webactivitydetail_sf_webactivity' => [
            'lhs_module' => 'sf_webActivity',
            'lhs_table' => 'sf_webactivity',
            'lhs_key' => 'id',
            'rhs_module' => 'sf_WebActivityDetail',
            'rhs_table' => 'sf_webactivitydetail',
            'rhs_key' => 'id',
            'relationship_type' => 'one-to-many',
            'join_table' => 'sf_webactivitydetail_sf_webactivity',
            'join_key_lhs' => 'sf_webactivitydetail_sf_webactivitysf_webactivity_ida',
            'join_key_rhs' => 'sf_webactivitydetail_sf_webactivitysf_webactivitydetail_idb',
            'true_relationship_type' => 'one-to-many',
        ],
    ],
];
