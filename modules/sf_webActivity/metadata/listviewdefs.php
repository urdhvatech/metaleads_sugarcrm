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

$module_name = 'sf_webActivity';
$listViewDefs[$module_name] = [
  'NAME' => [
    'type' => 'name',
    'link' => true,
    'label' => 'LBL_NAME',
    'width' => '10%',
    'default' => true,
  ],
  'STARTDATE' => [
    'type' => 'datetimecombo',
    'label' => 'LBL_STARTDATE',
    'width' => '10%',
    'default' => true,
  ],
  'REFERRERKEYWORDS' => [
    'type' => 'text',
    'studio' => 'visible',
    'label' => 'LBL_REFERRERKEYWORDS',
    'sortable' => false,
    'width' => '10%',
    'default' => true,
  ],
  'TOUCHPOINT' => [
    'type' => 'varchar',
    'label' => 'LBL_TOUCHPOINT',
    'width' => '10%',
    'default' => true,
  ],
  'CITY' => [
    'type' => 'varchar',
    'label' => 'LBL_CITY',
    'width' => '10%',
    'default' => true,
  ],
  'REGION' => [
    'type' => 'varchar',
    'label' => 'LBL_REGION',
    'width' => '10%',
    'default' => true,
  ],
  'EMAILADDRESS' => [
    'type' => 'varchar',
    'label' => 'LBL_EMAILADDRESS',
    'width' => '10%',
    'default' => false,
  ],
  'JAVAENABLED' => [
    'type' => 'bool',
    'default' => false,
    'label' => 'LBL_JAVAENABLED',
    'width' => '10%',
  ],
  'CLIENTHOSTNAME' => [
    'type' => 'varchar',
    'label' => 'LBL_CLIENTHOSTNAME',
    'width' => '10%',
    'default' => false,
  ],
];
