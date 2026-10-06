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

$module_name = 'sf_EventManagement';
$listViewDefs[$module_name] = [
  'NAME' => [
    'width' => '32%',
    'label' => 'LBL_NAME',
    'default' => true,
    'link' => true,
  ],
  'LEAD_SOURCE' => [
    'type' => 'varchar',
    'label' => 'LBL_LEAD_SOURCE',
    'width' => '10%',
    'default' => true,
  ],
  'EVENT_DATE' => [
    'type' => 'datetimecombo',
    'label' => 'LBL_EVENT_DATE',
    'width' => '10%',
    'default' => true,
  ],
  'DURATION' => [
    'type' => 'int',
    'label' => 'LBL_DURATION',
    'width' => '10%',
    'default' => true,
  ],
  'ATTENDED' => [
    'type' => 'bool',
    'default' => true,
    'label' => 'LBL_ATTENDED',
    'width' => '10%',
  ],
  'REGISTERED' => [
    'type' => 'bool',
    'default' => true,
    'label' => 'LBL_REGISTERED',
    'width' => '10%',
  ],
  'ASSIGNED_USER_NAME' => [
    'width' => '9%',
    'label' => 'LBL_ASSIGNED_TO_NAME',
    'module' => 'Employees',
    'id' => 'ASSIGNED_USER_ID',
    'default' => true,
  ],
  'EVENT_LOCATION' => [
    'type' => 'varchar',
    'label' => 'LBL_EVENT_LOCATION',
    'width' => '10%',
    'default' => false,
  ],
  'SF_EVENTMANAGEMENT_LEADS_NAME' => [
    'type' => 'relate',
    'link' => true,
    'label' => 'LBL_SF_EVENTMANAGEMENT_LEADS_FROM_LEADS_TITLE',
    'id' => 'SF_EVENTMANAGEMENT_LEADSLEADS_IDA',
    'width' => '10%',
    'default' => false,
  ],
  'SF_EVENTMANAGEMENT_CONTACTS_NAME' => [
    'type' => 'relate',
    'link' => true,
    'label' => 'LBL_SF_EVENTMANAGEMENT_CONTACTS_FROM_CONTACTS_TITLE',
    'id' => 'SF_EVENTMANAGEMENT_CONTACTSCONTACTS_IDA',
    'width' => '10%',
    'default' => false,
  ],
];
