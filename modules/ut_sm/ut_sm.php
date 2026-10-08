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

/**
 * Lightweight module bean so sidecar metadata can load Meta Leads admin screens.
 *
 * This bean has no table of its own. The Meta Leads tables are defined in
 * custom/metadata/ut_sm_tablesMetaData.php and created by Quick Repair from
 * the table dictionary. Pointing table_name at one of those tables makes
 * repair mark it as already handled and skip the CREATE TABLE statement.
 */
class UT_SM extends SugarBean
{
    public $module_dir = 'ut_sm';
    public $object_name = 'UT_SM';
    public $table_name = '';
    public $new_schema = false;
    public $disable_custom_fields = true;
    public $importable = false;

    public function __construct()
    {
        parent::__construct();
        $this->disable_row_level_security = true;
    }
}
