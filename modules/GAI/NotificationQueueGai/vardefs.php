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

$dictionary['NotificationQueueGai'] = [
    'table' => 'notification_queue_gai',
    'archive' => false,
    'activity_enabled' => false,
    'reassignable' => false,
    'duplicate_merge' => false,
    'unified_search' => false,
    'unified_search_default_enabled' => false,
    'fields' => [
        'id' => [
            'name' => 'id',
            'vname' => 'LBL_ID',
            'type' => 'id',
            'required' => true,
            'reportable' => false,
            'duplicate_on_record_copy' => 'no',
            'comment' => 'Unique identifier',
            'mandatory_fetch' => true,
        ],
        'date_entered' => [
            'name' => 'date_entered',
            'vname' => 'LBL_DATE_ENTERED',
            'type' => 'datetime',
            'group' => 'created_by_name',
            'comment' => 'Date record created',
            'enable_range_search' => true,
            'options' => 'date_range_search_dom',
            'studio' => [
                'portaleditview' => false,
            ],
            'duplicate_on_record_copy' => 'no',
            'readonly' => true,
            'massupdate' => false,
            'full_text_search' => [
                'enabled' => true,
                'searchable' => false,
            ],
        ],
        'date_modified' => [
            'name' => 'date_modified',
            'vname' => 'LBL_DATE_MODIFIED',
            'type' => 'datetime',
            'group' => 'modified_by_name',
            'comment' => 'Date record last modified',
            'enable_range_search' => true,
            'full_text_search' => [
                'enabled' => true,
                'searchable' => false,
                'sortable' => true,
            ],
            'studio' => [
                'portaleditview' => false,
            ],
            'options' => 'date_range_search_dom',
            'duplicate_on_record_copy' => 'no',
            'readonly' => true,
            'massupdate' => false,
        ],
        'deleted' => [
            'name' => 'deleted',
            'vname' => 'LBL_DELETED',
            'type' => 'bool',
            'default' => '0',
            'reportable' => false,
            'duplicate_on_record_copy' => 'no',
            'comment' => 'Record deletion indicator',
        ],
        'parent_id' => [
            'required' => true,
            'name' => 'parent_id',
            'vname' => 'LBL_PARENT_ID',
            'reportable' => false,
            'type' => 'id',
        ],
        'parent_type' => [
            'required' => true,
            'name' => 'parent_type',
            'vname' => 'LBL_PARENT_TYPE',
            'type' => 'varchar',
            'massupdate' => false,
            'default' => '',
            'no_default' => false,
            'comments' => '',
            'help' => '',
            'importable' => 'false',
            'duplicate_merge' => 'disabled',
            'duplicate_merge_dom_value' => '0',
            'audited' => false,
            'merge_filter' => 'disabled',
            'reportable' => false,
        ],
        'severity' => [
            'len' => 15,
            'name' => 'severity',
            'options' => 'notifications_severity_list',
            'required' => true,
            'type' => 'enum',
            'massupdate' => false,
            'vname' => 'LBL_GAI_SEVERITY',
            'readonly' => false,
        ],
        'notification_user_id' => [
            'required' => true,
            'name' => 'notification_user_id',
            'vname' => 'LBL_NOTIFICATION_USER_ID',
            'reportable' => false,
            'type' => 'id',
        ],
    ],
    'indices' => [
        'id' => [
            'name' => 'idx_notify_sumz_pk',
            'type' => 'primary',
            'fields' => ['id'],
        ],
        'date_modified' => [
            'name' => 'idx_notify_sumz_del_d_m',
            'type' => 'index',
            'fields' => ['deleted', 'date_modified', 'id'],
        ],
        'deleted' => [
            'name' => 'idx_notify_sumz_id_del',
            'type' => 'index',
            'fields' => ['id', 'deleted'],
        ],
        'date_entered' => [
            'name' => 'idx_notify_sumz_del_d_e',
            'type' => 'index',
            'fields' => ['deleted', 'date_entered', 'id'],
        ],
        'check_notify_state' => [
            'name' => 'idx_sumz_check_notify_state',
            'type' => 'index',
            'fields' => ['parent_id', 'parent_type', 'notification_user_id', 'deleted'],
        ],
    ],
    'relationships' => [],
    'optimistic_locking' => true,
    'portal_visibility' => [],
    'ignore_templates' => [
        'integrate_fields',
        'default',
    ],
    'uses' => [],
];

VardefManager::createVardef('NotificationQueueGai', 'NotificationQueueGai');
