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
/**
 * SuiteCRM controller for the ut_sm Meta Lead Ads module.
 */
if (!defined('sugarEntry') || !sugarEntry) {
    die('Not A Valid Entry Point');
}

require_once 'include/MVC/Controller/SugarController.php';

class ut_MetaLeadSubmissionsController extends SugarController
{
    public function action_editview()
    {
        $this->view = 'detail';
    }
}
