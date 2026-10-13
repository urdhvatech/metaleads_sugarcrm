<?php
/**
 * This file is part of the "Meta Leads for SugarAI" package.
 *
 * @package Meta Leads for SugarAI
 * @author Urdhva Tech <contact@urdhva-tech.com>
 * @link https://www.urdhva-tech.com
 * @copyright Urdhva Tech
 * @license As specified in the License Agreement supplied with this package.
 */

$dictionary["ut_sm_lead_imports"] = array (
  'table' => 'ut_sm_lead_imports',
  'module' => 'ut_sm',
  'fields' =>
  array (
    array (
      'name' => 'id',
      'type' => 'id',
      'required' => true,
    ),
    array (
      'name' => 'date_entered',
      'type' => 'datetime',
    ),
    array (
      'name' => 'date_modified',
      'type' => 'datetime',
    ),
    array (
      'name' => 'deleted',
      'type' => 'bool',
      'len' => '1',
      'default' => '0',
      'required' => true,
    ),
    array (
      'name' => 'leadgen_id',
      'type' => 'varchar',
      'len' => '64',
      'required' => true,
    ),
    array (
      'name' => 'page_id',
      'type' => 'varchar',
      'len' => '64',
    ),
    array (
      'name' => 'form_id',
      'type' => 'varchar',
      'len' => '64',
    ),
    array (
      'name' => 'account_id',
      'type' => 'id',
    ),
    array (
      'name' => 'meta_created_time',
      'type' => 'datetime',
    ),
    array (
      'name' => 'first_detected',
      'type' => 'datetime',
    ),
    array (
      'name' => 'last_attempted',
      'type' => 'datetime',
    ),
    array (
      'name' => 'import_status',
      'type' => 'varchar',
      'len' => '32',
      'default' => 'pending',
    ),
    array (
      'name' => 'retry_count',
      'type' => 'int',
      'default' => '0',
    ),
    array (
      'name' => 'error_message',
      'type' => 'text',
    ),
    array (
      'name' => 'lead_id',
      'type' => 'id',
    ),
    array (
      'name' => 'contact_id',
      'type' => 'id',
    ),
    array (
      'name' => 'import_source',
      'type' => 'varchar',
      'len' => '32',
    ),
  ),
  'indices' =>
  array (
    array (
      'name' => 'ut_sm_lead_importspk',
      'type' => 'primary',
      'fields' =>
      array (
        0 => 'id',
      ),
    ),
    array (
      'name' => 'idx_ut_sm_li_leadgen',
      'type' => 'index',
      'fields' =>
      array (
        0 => 'leadgen_id',
        1 => 'deleted',
      ),
    ),
    array (
      'name' => 'idx_ut_sm_li_status',
      'type' => 'index',
      'fields' =>
      array (
        0 => 'import_status',
        1 => 'deleted',
      ),
    ),
    array (
      'name' => 'idx_ut_sm_li_form',
      'type' => 'index',
      'fields' =>
      array (
        0 => 'form_id',
        1 => 'deleted',
      ),
    ),
    array (
      'name' => 'idx_ut_sm_li_contact',
      'type' => 'index',
      'fields' =>
      array (
        0 => 'contact_id',
        1 => 'deleted',
      ),
    ),
  ),
);