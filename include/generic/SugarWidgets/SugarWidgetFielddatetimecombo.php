<?php
/*
 * Your installation or use of this SugarCRM file is subject to the applicable
 * terms available at
 * http://support.sugarcrm.com/Resources/Master_Subscription_Agreements/.
 * If you do not agree to all of the applicable terms or do not have the
 * authority to bind the entity as an authorized representative, then do not
 * install or use this SugarCRM file.
 *
 * Copyright (C) SugarCRM Inc. All rights reserved.
 */

use Sugarcrm\Sugarcrm\Reports\ReportFormatterFactory;

class SugarWidgetFieldDateTimecombo extends SugarWidgetFieldDateTime
{
    public $reporter;
    public $assigned_user = null;

    public function __construct(&$layout_manager)
    {
        parent::__construct($layout_manager);
        $this->reporter = $this->layout_manager->getAttribute('reporter');
    }
    //TODO:now for date time field , we just search from date start to date end. The time is from 00:00:00 to 23:59:59
    //If there is requirement, we can modify report.js::addFilterInputDatetimesBetween and this function
    public function queryFilterBetween_Datetimes(&$layout_def)
    {
        $type = $layout_def['type'];

        if ($this->getAssignedUser()) {
            $begin = $layout_def['input_name0'];
            $end = $layout_def['input_name2'];
        } else {
            $begin = $layout_def['input_name0'];
            $end = $layout_def['input_name1'];
        }

        $reportFormatter = ReportFormatterFactory::getFormatter($type);

        $begin = $reportFormatter->getDBDatetimeFromUserInput($begin);
        $end = $reportFormatter->getDBDatetimeFromUserInput($end);

        return '(' . $this->_get_column_select($layout_def) . '>=' . $this->reporter->db->convert($this->reporter->db->quoted($begin), $type) .
            " AND\n " . $this->_get_column_select($layout_def) . '<=' . $this->reporter->db->convert($this->reporter->db->quoted($end), $type) .
            ")\n";
    }

    /**
     * Get datetime value for sidecar field
     *
     * @param array $layoutDef
     *
     * @return mixed
     */
    public function getFieldControllerData(array $layoutDef)
    {
        $value = parent::getFieldControllerData($layoutDef);

        return $value;
    }
}
