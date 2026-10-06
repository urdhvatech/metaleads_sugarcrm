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

$module_name = 'sf_Dialogs';
$listViewDefs[$module_name] = [
  'NAME' => [
    'width' => '32%',
    'label' => 'LBL_NAME',
    'default' => true,
    'link' => true,
  ],
  'DIALOG_ID' => [
    'type' => 'int',
    'label' => 'LBL_DIALOG_ID',
    'width' => '10%',
    'default' => true,
  ],
  'START_FORM_ID' => [
    'type' => 'int',
    'label' => 'LBL_START_FORM_ID',
    'width' => '10%',
    'default' => true,
  ],
  'COMPLETED_DATE' => [
    'type' => 'datetimecombo',
    'label' => 'LBL_COMPLETED_DATE',
    'width' => '10%',
    'default' => true,
  ],
  'ASSIGNED_USER_NAME' => [
    'width' => '9%',
    'label' => 'LBL_ASSIGNED_TO_NAME',
    'module' => 'Employees',
    'id' => 'ASSIGNED_USER_ID',
    'default' => true,
  ],
  'TEAM_NAME' => [
    'width' => '9%',
    'label' => 'LBL_TEAM',
    'default' => false,
  ],
];
