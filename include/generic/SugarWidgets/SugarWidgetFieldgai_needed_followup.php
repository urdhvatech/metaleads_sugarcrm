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

class SugarWidgetFieldgai_needed_followup extends SugarWidgetFieldText
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
                $prettyPrint = $this->renderNeededFollowup($decodedData);

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
     * Render the needed follow-up field as HTML
     *
     * @param array $data
     * @return string
     */
    protected function renderNeededFollowup(array $data)
    {
        $ss = new \Sugar_Smarty();

        $ss->assign('data', $data);

        $htmlTemplate = to_html($ss->fetch('src/GAI/Templates/Needed-followupTemplate.tpl'));

        return $htmlTemplate;
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
