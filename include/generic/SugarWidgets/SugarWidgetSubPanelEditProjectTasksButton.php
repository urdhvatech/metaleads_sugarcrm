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


class SugarWidgetSubPanelEditProjectTasksButton extends SugarWidgetSubPanelTopButton
{
    public function getDisplayName()
    {
        return translate('LBL_VIEW_GANTT_TITLE', 'Project');
    }

    public function getWidgetId()
    {
        return 'project_task_submit_button';
    }

    public function display(array $widget_data, $additionalFormFields = [])
    {
        $title = translate('LBL_VIEW_GANTT_TITLE', 'Project');
        $value = $this->getDisplayName();
        $module_name = 'Project';
        $id = $widget_data['focus']->id;

        return '<form action="index.php" method="Post">'
            . '<input type="hidden" name="module" value="Project"> '
            . '<input type="hidden" name="action" value="EditGridView"> '
            . '<input type="hidden" name="return_module" value="Project" /> '
            . '<input type="hidden" name="return_action" value="DetailView" /> '
            . '<input type="hidden" name="project_id" value="' . $id . '" /> '
            . '<input type="hidden" name="return_id" value="' . $id . '" /> '
            . '<input type="hidden" name="record" value="' . $id . '" /> '
            . '<input type="submit" name="EditProjectTasks" '
            . ' class="button"'
            . ' id="' . $this->getWidgetId() . '"'
            . ' title="' . $title . '"'
            . ' value="' . $value . '" />'
            . '</form>';
    }
}
