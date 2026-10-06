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
$listViewDefs[$module_name]['templateMeta']['showMassUpdateButtons'] = false;
$listViewDefs[$module_name] = array(
    'NAME' => array(
        'width' => '20%',
        'label' => 'LBL_NAME',
        'default' => true,
        'link' => true,
    ),
    'META_LEADGEN_ID' => array(
        'width' => '15%',
        'label' => 'LBL_META_LEADGEN_ID',
        'default' => true,
    ),
    'FORM_NAME' => array(
        'width' => '15%',
        'label' => 'LBL_FORM_NAME',
        'default' => true,
    ),
    'PAGE_NAME' => array(
        'width' => '15%',
        'label' => 'LBL_PAGE_NAME',
        'default' => true,
    ),
    'PLATFORM' => array(
        'width' => '10%',
        'label' => 'LBL_PLATFORM',
        'default' => true,
    ),
    'SUBMITTED_AT' => array(
        'width' => '12%',
        'label' => 'LBL_SUBMITTED_AT',
        'default' => true,
    ),
    'LEAD_NAME' => array(
        'width' => '12%',
        'label' => 'LBL_LEAD_NAME',
        'default' => true,
        'module' => 'Leads',
        'id' => 'LEAD_ID',
        'link' => true,
        'related_fields' => array('lead_id'),
    ),
    'CONTACT_NAME' => array(
        'width' => '12%',
        'label' => 'LBL_CONTACT_NAME',
        'default' => true,
        'module' => 'Contacts',
        'id' => 'CONTACT_ID',
        'link' => true,
        'related_fields' => array('contact_id'),
    ),
);
