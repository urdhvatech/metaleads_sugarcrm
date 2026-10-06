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
 * API for Config Framework.
 */
class ConfigApi extends AdministrationApi
{
    /**
     * @return array
     */
    public function registerApiRest()
    {
        return [
            'getConfig' => [
                'reqType' => ['GET'],
                'path' => ['Administration', 'config', '?'],
                'pathVars' => ['', '', 'category'],
                'method' => 'getConfig',
                'shortHelp' => 'Gets configuration for a category',
                'longHelp' => 'include/api/help/administration_config_get_help.html',
                'exceptions' => ['SugarApiExceptionNotAuthorized'],
                'ignoreSystemStatusError' => true,
                'minVersion' => '11.13',
            ],
            'setConfig' => [
                'reqType' => ['POST'],
                'path' => ['Administration', 'config', '?'],
                'pathVars' => ['', '', 'category'],
                'method' => 'setConfig',
                'shortHelp' => 'Sets configuration for a category',
                'longHelp' => 'include/api/help/administration_config_post_help.html',
                'exceptions' => ['SugarApiExceptionNotAuthorized'],
                'ignoreSystemStatusError' => true,
                'minVersion' => '11.13',
            ],
        ];
    }

    /**
     * Gets configuration details for a category
     *
     * @param ServiceBase $api The RestService object
     * @param array $args Arguments passed to the service
     * @return array
     */
    public function getConfig(ServiceBase $api, array $args)
    {
        $this->requireArgs($args, ['category']);
        $this->ensureAdminUser();
        $handler = $this->getHandler($args['category']);
        return $handler->getConfig($api, $args);
    }

    /**
     * Saves new configuration details for a category and returns updated config
     *
     * @param ServiceBase $api The RestService object
     * @param array $args Arguments passed to the service
     * @return array
     */
    public function setConfig(ServiceBase $api, array $args)
    {
        $this->requireArgs($args, ['category']);

        if (($args['category'] ?? '') === 'timeline') {
            $this->ensureHasAccessToTimelineModules($api, $args);
        } else {
            $this->ensureAdminUser();
        }

        $handler = $this->getHandler($args['category']);
        return $handler->setConfig($api, $args);
    }

    /**
     * Gets api handler.
     * @param string $category
     * @return object
     */
    protected function getHandler(string $category)
    {
        $handlerClass = ucfirst($category) . 'ConfigApiHandler';
        if (!class_exists($handlerClass)) {
            $handlerClass = 'ConfigApiHandler';
        }
        return new $handlerClass();
    }

    /**
     * Check if the user has access to modules specified in the args
     *
     * @param ServiceBase $api
     * @param array $args
     * @return void
     * @throws SugarApiExceptionNotAuthorized
     */
    protected function ensureHasAccessToTimelineModules(ServiceBase $api, array $args): void
    {
        global $app_strings;
        $module = $this->extractModuleFromArgs($args);

        if (empty($module) || !$this->checkAccess($module)) {
            throw new \SugarApiExceptionNotAuthorized($app_strings['EXCEPTION_NOT_AUTHORIZED']);
        }

        $enabledModulesSaved = $this->getSavedEnabledModules($module);

        $enabledModules = $args["timeline_{$module}"]['enabledModules'] ?? [];
        if (!is_array($enabledModules) || empty($enabledModules)) {
            throw new \SugarApiExceptionNotAuthorized($app_strings['EXCEPTION_NOT_AUTHORIZED']);
        }

        // Compare modules from the request with activated (saved or used by default) modules.
        // There is no sense to check access for all modules from a request.
        // User might not have access to some of them, we only check modules that are trying
        // to be changed (enabled or disabled) in the request.
        $diffModules = array_merge(
            array_diff($enabledModules, $enabledModulesSaved),
            array_diff($enabledModulesSaved, $enabledModules)
        );

        $bean = $this->getBean($module);

        foreach ($diffModules as $link) {
            if (!$bean->load_relationship($link)) {
                throw new \SugarApiExceptionNotAuthorized($app_strings['EXCEPTION_NOT_AUTHORIZED']);
            }

            $moduleName = $bean->$link->getRelatedModuleName();
            if (!$this->checkAccess($moduleName)) {
                throw new \SugarApiExceptionNotAuthorized($app_strings['EXCEPTION_NOT_AUTHORIZED']);
            }
        }
    }

    /** Get saved timeline enabled modules
     *
     * @param string $module
     * @return array
     */
    protected function getSavedEnabledModules(string $module): array
    {
        $mdm = \MetaDataManager::getManager();
        $config = $mdm->getTimelineConfig();
        if (!is_array($config[$module] ?? null)) {
            return [];
        }

        return $config[$module]['enabledModules'] ?? [];
    }

    /**
     * Extract module name from arguments
     *
     * @param array $args
     * @return string
     */
    private function extractModuleFromArgs(array $args): string
    {
        foreach ($args as $key => $value) {
            if (str_starts_with($key, 'timeline_')) {
                return str_replace('timeline_', '', $key);
            }
        }

        return '';
    }

    /**
     * Get a new SugarBean instance for the specified module
     *
     * @param string $module
     * @return SugarBean
     */
    protected function getBean(string $module): SugarBean
    {
        return BeanFactory::newBean($module);
    }

    /**
     * Check if the user has access to the specified module
     *
     * @param string $module
     * @return bool
     */
    protected function checkAccess(string $module): bool
    {
        global $current_user;
        return $current_user->isAdminForModule($module);
    }
}
