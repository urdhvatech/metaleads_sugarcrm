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

$dictionary['Lead']['fields']['ut_source_platform'] = array(
    'name' => 'ut_source_platform',
    'vname' => 'LBL_UT_SOURCE_PLATFORM',
    'type' => 'varchar',
    'len' => 30,
    'audited' => false,
    'reportable' => true,
    'massupdate' => true,
    'duplicate_merge' => 'enabled',
);

$dictionary['Lead']['fields']['ut_campaign_external_id'] = array(
    'name' => 'ut_campaign_external_id',
    'vname' => 'LBL_UT_CAMPAIGN_EXTERNAL_ID',
    'type' => 'varchar',
    'len' => 64,
    'audited' => false,
    'reportable' => true,
    'massupdate' => false,
);

$dictionary['Lead']['fields']['ut_ad_id'] = array(
    'name' => 'ut_ad_id',
    'vname' => 'LBL_UT_AD_ID',
    'type' => 'varchar',
    'len' => 64,
    'audited' => false,
    'reportable' => true,
    'massupdate' => false,
);

$dictionary['Lead']['fields']['ut_form_id'] = array(
    'name' => 'ut_form_id',
    'vname' => 'LBL_UT_FORM_ID',
    'type' => 'varchar',
    'len' => 64,
    'audited' => false,
    'reportable' => true,
    'massupdate' => false,
);

$dictionary['Lead']['fields']['ut_all_fields_json'] = array(
    'name' => 'ut_all_fields_json',
    'vname' => 'LBL_UT_ALL_FIELDS_JSON',
    'type' => 'longtext',
    'audited' => false,
    'reportable' => false,
    'massupdate' => false,
    'importable' => false,
);

$dictionary['Lead']['fields']['ut_leadgen_id'] = array(
    'name' => 'ut_leadgen_id',
    'vname' => 'LBL_UT_LEADGEN_ID',
    'type' => 'varchar',
    'len' => 64,
    'audited' => false,
    'reportable' => true,
    'massupdate' => false,
    'importable' => true,
);

$dictionary['Lead']['fields']['ut_campaign_name'] = array(
    'name' => 'ut_campaign_name',
    'vname' => 'LBL_UT_CAMPAIGN_NAME',
    'type' => 'varchar',
    'len' => 255,
    'audited' => false,
    'reportable' => true,
    'massupdate' => false,
);

$dictionary['Lead']['indices']['idx_leads_ut_leadgen_id'] = array(
    'name' => 'idx_leads_ut_leadgen_id',
    'type' => 'index',
    'fields' => array('ut_leadgen_id'),
);
