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
if (!defined('sugarEntry') || !sugarEntry) {
    die('Not A Valid Entry Point');
}

require_once 'include/MVC/View/views/view.list.php';

class ut_MetaLeadSubmissionsViewList extends ViewList
{
    public function preDisplay()
    {
        parent::preDisplay();

        # Hide Quick Edit Pencil
        $this->lv->export = false;
        $this->lv->showMassupdateFields = false;
        $this->lv->editviewlink = false;
    }
}
