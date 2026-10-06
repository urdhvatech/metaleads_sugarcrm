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

class TemplateHtmlEditableTinymce extends TemplateField
{
    public $type = 'htmleditable_tinymce';
    public $dbType = 'longtext';
    public $studio = true;

    /**
     * Override get_field_def to ensure no len property is added
     * htmleditable_tinymce fields should use longtext storage without length constraints
     */
    public function get_field_def()
    {
        $def = parent::get_field_def();
        
        // Remove any len property that might have been set
        // This ensures longtext fields don't get length constraints
        unset($def['len']);
        
        return $def;
    }
}
