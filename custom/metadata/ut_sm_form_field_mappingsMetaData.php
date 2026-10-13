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

$dictionary["ut_sm_form_field_mappings"] = array (
  'table' => 'ut_sm_form_field_mappings',
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
      'name' => 'account_id',
      'type' => 'id',
      'required' => true,
    ),
    array (
      'name' => 'form_id',
      'type' => 'varchar',
      'len' => '64',
      'required' => true,
    ),
    array (
      'name' => 'meta_field_key',
      'type' => 'varchar',
      'len' => '255',
      'required' => true,
    ),
    array (
      'name' => 'meta_field_label',
      'type' => 'varchar',
      'len' => '255',
    ),
    array (
      'name' => 'meta_field_type',
      'type' => 'varchar',
      'len' => '64',
    ),
    array (
      'name' => 'crm_field',
      'type' => 'varchar',
      'len' => '100',
    ),
    array (
      'name' => 'sort_order',
      'type' => 'int',
      'default' => '0',
    ),
  ),
  'indices' =>
  array (
    array (
      'name' => 'ut_sm_form_field_mappingspk',
      'type' => 'primary',
      'fields' =>
      array (
        0 => 'id',
      ),
    ),
    array (
      'name' => 'idx_ut_sm_ffm_account',
      'type' => 'index',
      'fields' =>
      array (
        0 => 'account_id',
        1 => 'deleted',
      ),
    ),
    array (
      'name' => 'idx_ut_sm_ffm_form',
      'type' => 'index',
      'fields' =>
      array (
        0 => 'account_id',
        1 => 'form_id',
        2 => 'deleted',
      ),
    ),
    array (
      'name' => 'idx_ut_sm_ffm_key',
      'type' => 'index',
      'fields' =>
      array (
        0 => 'account_id',
        1 => 'form_id',
        2 => 'meta_field_key',
      ),
    ),
  ),
);