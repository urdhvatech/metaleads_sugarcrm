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

$searchFields['ut_MetaLeadSubmissions'] = array(
    'name' => array('query_type' => 'default'),
    'meta_leadgen_id' => array('query_type' => 'default'),
    'form_name' => array('query_type' => 'default'),
    'page_name' => array('query_type' => 'default'),
    'platform' => array('query_type' => 'default'),
    'campaign_name' => array('query_type' => 'default'),
    'current_user_only' => array(
        'query_type' => 'default',
        'db_field' => array('assigned_user_id'),
        'my_items' => true,
        'vname' => 'LBL_CURRENT_USER_FILTER',
        'type' => 'bool',
    ),
    'assigned_user_id' => array('query_type' => 'default'),
);
