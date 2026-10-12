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

$viewdefs['ut_MetaLeadSubmissions']['base']['view']['subpanel-list'] = array(
    'panels' => array(
        array(
            'name' => 'panel_header',
            'label' => 'LBL_PANEL_1',
            'fields' => array(
                array(
                    'name' => 'name',
                    'label' => 'LBL_FORM_NAME',
                    'enabled' => true,
                    'default' => true,
                    'link' => true,
                ),
                array(
                    'name' => 'meta_leadgen_id',
                    'label' => 'LBL_META_LEADGEN_ID',
                    'enabled' => true,
                    'default' => true,
                    'link' => true,
                ),
                array(
                    'name' => 'page_name',
                    'label' => 'LBL_PAGE_NAME',
                    'enabled' => true,
                    'default' => true,
                ),
                array(
                    'name' => 'platform',
                    'label' => 'LBL_PLATFORM',
                    'enabled' => true,
                    'default' => true,
                ),
                array(
                    'name' => 'submitted_at',
                    'label' => 'LBL_SUBMITTED_AT',
                    'enabled' => true,
                    'default' => true,
                    'type' => 'datetime',
                ),
            ),
        ),
    ),
    'orderBy' => array(
        'field' => 'submitted_at',
        'direction' => 'desc',
    ),
);
