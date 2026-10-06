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

use Sugarcrm\Sugarcrm\ProcessManager;

class PMSELogicHook
{
    public function after_save($bean, $event, $arguments)
    {
        if (!$this->isSugarInstalled()) {
            return true;
        }

        if (!PMSEEngineUtils::hasActiveProcesses($bean)) {
            return true;
        }
        //Define PA Hook Handler
        $handler = ProcessManager\Factory::getPMSEObject('PMSEHookHandler');
        return $handler->runStartEventAfterSave($bean, $event, $arguments);
    }

    public function after_delete($bean, $event, $arguments)
    {
        if (!$this->isSugarInstalled()) {
            return true;
        }

        if (!PMSEEngineUtils::hasActiveProcesses($bean)) {
            return true;
        }
        //Define PA Hook Handler
        $handler = ProcessManager\Factory::getPMSEObject('PMSEHookHandler');
        return $handler->terminateCaseAfterDelete($bean, $event, $arguments);
    }

    /**
     * Function for relationship hook trigger
     * @param SugarBean $bean
     * @param string $event
     * @param array $arguments
     * @return bool
     */
    public function after_relationship($bean, $event, $arguments)
    {
        if (!$this->isSugarInstalled()) {
            return true;
        }

        if (!PMSEEngineUtils::hasActiveProcesses($bean)) {
            return true;
        }

        $relatedModule = $arguments['related_module_name'] ?? '';
        if (!$this->validateRelationshipChangeModules($bean->getModuleName(), $relatedModule)) {
            return true;
        }

        //Define PA Hook Handler
        $handler = ProcessManager\Factory::getPMSEObject('PMSEHookHandler');
        return $handler->runStartEventAfterRelationship($bean, $event, $arguments);
    }

    /**
     * Validates that the set of modules involved in a relationship change
     * event contain only modules that should be allowed to trigger a BPM
     * relationship change event
     *
     * @param string $primaryModule the name of the primary module involved
     * @param string $relatedModule the name of the related module involved
     * @return bool true if the modules are both valid; false otherwise
     */
    protected function validateRelationshipChangeModules($primaryModule, $relatedModule) : bool
    {
        // BPM creates and removes relationships from the target bean to BPM internal modules
        // during a process. BPM module relationship changes should not be allowed to trigger a
        // BPM relationship change event as they are only used internally, and this can lead to
        // an infinite loop
        return !PMSEEngineUtils::isPMSEModule($primaryModule) && !PMSEEngineUtils::isPMSEModule($relatedModule);
    }

    /**
     * Checks to see if Sugar is installed. Returns false when Sugar is in the process
     * of installation
     * @return boolean
     */
    protected function isSugarInstalled()
    {
        global $sugar_config;

        // During installation, the `installing` variable is set, so if this is
        // not empty, then we are in the middle of installation, or not installed
        if (!empty($GLOBALS['installing'])) {
            return false;
        }

        // When installed, sugar sets `installer_locked` in the config to true,
        // so if `installer_locked` is not empty then we are installed
        return !empty($sugar_config['installer_locked']);
    }
}
