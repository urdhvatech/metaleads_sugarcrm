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

$dictionary['Lead']['fields']['ut_metaleadsubmissions'] = array(
    'name' => 'ut_metaleadsubmissions',
    'type' => 'link',
    'relationship' => 'lead_ut_metaleadsubmissions',
    'source' => 'non-db',
    'module' => 'ut_MetaLeadSubmissions',
    'bean_name' => 'ut_MetaLeadSubmissions',
    'side' => 'right',
    'vname' => 'LBL_UT_METALEADSUBMISSIONS_SUBPANEL_TITLE',
);
