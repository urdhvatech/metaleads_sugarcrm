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

$dictionary["ut_sm_page_subscriptions"] = array (
  'table' => 'ut_sm_page_subscriptions',
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
      'name' => 'page_id',
      'type' => 'varchar',
      'len' => '64',
      'required' => true,
    ),
    array (
      'name' => 'page_name',
      'type' => 'varchar',
      'len' => '255',
      'required' => true,
    ),
    array (
      'name' => 'page_access_token',
      'type' => 'text',
      'required' => true,
    ),
    array (
      'name' => 'account_type',
      'type' => 'varchar',
      'len' => '32',
      'default' => 'facebook',
      'required' => true,
    ),
    array (
      'name' => 'instagram_account_id',
      'type' => 'varchar',
      'len' => '64',
    ),
    array (
      'name' => 'assignment_type',
      'type' => 'varchar',
      'len' => '32',
      'default' => 'keep_empty',
    ),
    array (
      'name' => 'assignment_user_id',
      'type' => 'id',
    ),
    array (
      'name' => 'assignment_group_id',
      'type' => 'id',
    ),
    array (
      'name' => 'assignment_rr_user_ids',
      'type' => 'text',
    ),
  ),
  'indices' =>
  array (
    array (
      'name' => 'ut_sm_page_subscriptionspk',
      'type' => 'primary',
      'fields' =>
      array (
        0 => 'id',
      ),
    ),
    array (
      'name' => 'idx_ut_sm_page_subscriptions_page_name',
      'type' => 'index',
      'fields' =>
      array (
        0 => 'page_name',
      ),
    ),
    array (
      'name' => 'idx_ut_sm_page_subscriptions_del',
      'type' => 'index',
      'fields' =>
      array (
        0 => 'deleted',
      ),
    ),
    array (
      'name' => 'idx_ut_sm_page_acct_type',
      'type' => 'index',
      'fields' =>
      array (
        0 => 'page_id',
        1 => 'account_type',
      ),
    ),
  ),
);