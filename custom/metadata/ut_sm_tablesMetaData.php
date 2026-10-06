<?php
/**
 * This file is part of the "Meta Leads" package.
 *
 * @package Meta Leads
 * @author Urdhva Tech <contact@urdhva-tech.com>
 * @link https://www.urdhva-tech.com
 * @copyright Urdhva Tech
 * @license As specified in the License Agreement supplied with this package.
 */

$dictionary["ut_sm_page_subscriptions"] = array (
  'table' => 'ut_sm_page_subscriptions',
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

$dictionary["ut_sm_account_forms"] = array (
  'table' => 'ut_sm_account_forms',
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
      'name' => 'form_name',
      'type' => 'varchar',
      'len' => '255',
      'required' => true,
    ),
    array (
      'name' => 'enabled',
      'type' => 'bool',
      'len' => '1',
      'default' => '0',
      'required' => true,
    ),
  ),
  'indices' =>
  array (
    array (
      'name' => 'ut_sm_account_formspk',
      'type' => 'primary',
      'fields' =>
      array (
        0 => 'id',
      ),
    ),
    array (
      'name' => 'idx_ut_sm_acct_forms_account',
      'type' => 'index',
      'fields' =>
      array (
        0 => 'account_id',
        1 => 'deleted',
      ),
    ),
    array (
      'name' => 'idx_ut_sm_acct_forms_form',
      'type' => 'index',
      'fields' =>
      array (
        0 => 'account_id',
        1 => 'form_id',
      ),
    ),
  ),
);

$dictionary["ut_sm_form_field_mappings"] = array (
  'table' => 'ut_sm_form_field_mappings',
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

$dictionary["ut_sm_lead_imports"] = array (
  'table' => 'ut_sm_lead_imports',
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
