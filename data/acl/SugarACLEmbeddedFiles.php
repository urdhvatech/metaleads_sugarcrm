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

/**
 * ACL for EmbeddedFiles
 */
class SugarACLEmbeddedFiles extends SugarACLOwnerWrite
{
    /**
     * @param $module
     * @param $view
     * @param $context
     * @return bool
     */
    public function checkAccess($module, $view, $context)
    {
        if ($view === 'field') {
            // no field level check
            return true;
        }

        if (!parent::checkAccess($module, $view, $context)) {
            return false;
        }

        if (!$this->isWriteOperation($view, $context)) {
            if (static::fixUpActionName($view) === 'list') {
                // ListView is available only for admin users
                $user = $this->getCurrentUser($context);
                return $user->isAdminForModule($module);
            }
        }
        return true;
    }
}
