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
$vardefs = [
    'fields' => [
        'last_interaction_date' => [
            'name' => 'last_interaction_date',
            'vname' => 'LBL_LAST_INTERACTION',
            'type' => 'datetime',
            'reportable' => true,
            'audited' => true,
            'readonly' => true,
            'linked_fields' => [
                'last_interaction_parent_id',
                'last_interaction_parent_name',
                'last_interaction_parent_type',
            ],
        ],
        'last_interaction_parent_type' => [
            'name' => 'last_interaction_parent_type',
            'vname' => 'LBL_PARENT_TYPE',
            'type' => 'parent_type',
            'dbType' => 'varchar',
            'len' => 30,
            'group' => 'parent_name',
            'options' => 'parent_type_display',
            'reportable' => true,
            'audited' => true,
            'studio' => false,
            'comment' => 'Identifier of Sugar module to which last interaction is associated',
        ],
        'last_interaction_parent_id' => [
            'name' => 'last_interaction_parent_id',
            'vname' => 'LBL_RECORD_ID',
            'type' => 'id',
            'group' => 'parent_name',
            'reportable' => true,
            'audited' => true,
            'studio' => false,
            'comment' => 'Identifier of Sugar record to which last interaction is associated',
        ],
        'last_interaction_parent_name' => [
            'name' => 'last_interaction_parent_name',
            'vname' => 'LBL_LAST_INTERACTION_PARENT_NAME',
            'type' => 'parent_type',
            'dbType' => 'varchar',
            'len' => 255,
            'reportable' => true,
            'audited' => true,
            'studio' => false,
            'comment' => 'Tooltip that will be displayed when hovering on last_interaction column in grids',
        ],
    ],
];
