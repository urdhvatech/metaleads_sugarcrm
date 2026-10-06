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

use Sugarcrm\Sugarcrm\Security\Escaper\Escape;

class SugarWidgetFieldgai_summary extends SugarWidgetFieldText
{
    /**
     * @inheritdoc
     */
    public function display(array $layout_def)
    {
        $parent = parent::displayList($layout_def);

        // Check if this is being called from ReportSchedules (for email reports)
        // If so, return raw JSON data instead of formatted HTML
        if ($this->isEmailScheduleContext()) {
            return $parent;
        }

        $decodedData = json_decode($parent, true);

        if ($decodedData && is_array($decodedData) && safeCount($decodedData) > 0) {
            try {
                $prettyPrint = $this->summaryPrettyPrint($decodedData);

                if ($prettyPrint) {
                    return $prettyPrint;
                }
            } catch (Exception $e) {
                return $parent;
            }
        }

        return $parent;
    }

    /**
     * @inheritdoc
     */
    public function displayList($layout_def)
    {
        return $this->display($layout_def);
    }

    /**
     * Render a pretty-printed summary
     *
     * @param array $summary
     * @return string
     */
    protected function summaryPrettyPrint(array $summary)
    {
        $ss = new \Sugar_Smarty();

        $ss->register_modifier('isAssocArray', function ($array) {
            return $this->isAssocArray($array);
        });

        $ss->register_modifier('containsObjects', function ($array) {
            return $this->containsObjects($array);
        });

        $ss->assign('summary', $summary);

        $htmlTemplate = to_html($ss->fetch('src/GAI/Templates/SummaryTemplate.tpl'));

        return $htmlTemplate;
    }

    /**
     * Check if the array is associative
     *
     * @param array $array
     * @return bool
     */
    private function isAssocArray(array $array)
    {
        return array_keys($array) !== range(0, count($array) - 1);
    }

    /**
     * Check if the array contains objects (associative arrays)
     *
     * @param array $array
     * @return bool
     */
    private function containsObjects(array $array)
    {
        return is_array($array) && count($array) > 0 && is_array($array[0]) && $this->isAssocArray($array[0]);
    }

    /**
     * Check if we're in an email schedule context to return raw JSON instead of formatted HTML
     *
     * @return bool
     */
    private function isEmailScheduleContext(): bool
    {
        return !empty($this->reporter->skipGaiWidgetFormatting);
    }
}
