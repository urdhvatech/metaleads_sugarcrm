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


//TODO Rename this to edit link
class SugarWidgetSubPanelRelFieldEditButton extends SugarWidgetField
{
    public function displayHeaderCell($layout_def)
    {
        return '&nbsp;';
    }

    public function displayList($layout_def)
    {
        die('<pre>' . print_r($layout_def, true) . '</pre>');
    }
}
