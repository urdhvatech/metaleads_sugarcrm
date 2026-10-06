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

$module_name = 'ut_MetaLeadSubmissions';
$searchdefs[$module_name] = array(
    'layout' => array(
        'basic_search' => array(
            'name',
            'meta_leadgen_id',
            'form_name',
            'page_name',
            array('name' => 'current_user_only', 'label' => 'LBL_CURRENT_USER_FILTER', 'type' => 'bool'),
        ),
        'advanced_search' => array(
            'name',
            'meta_leadgen_id',
            'form_id',
            'form_name',
            'page_id',
            'page_name',
            'platform',
            'campaign_name',
            'lead_name',
            'contact_name',
            'assigned_user_id',
        ),
    ),
);
