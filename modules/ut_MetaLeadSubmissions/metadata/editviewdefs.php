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
$viewdefs[$module_name]['EditView'] = array(
    'templateMeta' => array(
        'maxColumns' => '2',
        'widths' => array(
            array('label' => '10', 'field' => '30'),
            array('label' => '10', 'field' => '30'),
        ),
    ),
    'panels' => array(
        'default' => array(
            array('name', 'meta_leadgen_id'),
            array('form_name', 'form_id'),
            array('page_name', 'page_id'),
            array('platform', 'submitted_at'),
            array('campaign_name', 'campaign_id'),
            array('ad_id', 'adset_id'),
            array('lead_name', 'contact_name'),
            array('assigned_user_name', ''),
            array(
                array(
                    'name' => 'submitted_values',
                    'displayParams' => array(
                        'rows' => 10,
                        'cols' => 80,
                    ),
                ),
            ),
            array('description'),
        ),
    ),
);
