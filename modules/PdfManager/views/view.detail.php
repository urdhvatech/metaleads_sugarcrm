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

class PdfManagerViewDetail extends ViewDetail
{
    public function display()
    {
        // Assign variables for the preview template
        $this->ss->assign('body_html', $this->bean->body_html);

        // Process the preview template
        $previewHtml = $this->ss->fetch('modules/PdfManager/tpls/pdf-preview.tpl');
        $this->ss->assign('pdf_preview_content', $previewHtml);

        parent::display();
    }
}
