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

use Sugarcrm\Sugarcrm\AccessControl\AccessControlManager;

class DashboardsFilterApi extends FilterApi
{
    public function registerApiRest()
    {
        return [
            'filterModuleGet' => [
                'reqType' => 'GET',
                'path' => ['Dashboards', 'filter'],
                'pathVars' => ['module', ''],
                'method' => 'filterList',
                'jsonParams' => ['filter'],
                'shortHelp' => 'Lists filtered records.',
                'longHelp' => 'include/api/help/module_filter_get_help.html',
                'exceptions' => [
                    // Thrown in getPredefinedFilterById
                    'SugarApiExceptionNotFound',
                    'SugarApiExceptionError',
                    // Thrown in filterList and filterListSetup
                    'SugarApiExceptionInvalidParameter',
                    // Thrown in filterListSetup, getPredefinedFilterById, and parseArguments
                    'SugarApiExceptionNotAuthorized',
                ],
            ],
            'filterModuleAll' => [
                'reqType' => 'GET',
                'path' => ['Dashboards'],
                'pathVars' => ['module'],
                'method' => 'filterList',
                'jsonParams' => ['filter'],
                'shortHelp' => 'List of all records in this module',
                'longHelp' => 'include/api/help/module_filter_get_help.html',
                'exceptions' => [
                    // Thrown in getPredefinedFilterById
                    'SugarApiExceptionNotFound',
                    'SugarApiExceptionError',
                    // Thrown in filterList and filterListSetup
                    'SugarApiExceptionInvalidParameter',
                    // Thrown in filterListSetup, getPredefinedFilterById, and parseArguments
                    'SugarApiExceptionNotAuthorized',
                ],
            ],
            'filterModuleAllCount' => [
                'reqType' => 'GET',
                'path' => ['Dashboards', 'count'],
                'pathVars' => ['module', ''],
                'jsonParams' => ['filter'],
                'method' => 'getFilterListCount',
                'shortHelp' => 'List of all records in this module',
                'longHelp' => 'include/api/help/module_filter_get_help.html',
                'exceptions' => [
                    // Thrown in getPredefinedFilterById
                    'SugarApiExceptionNotFound',
                    'SugarApiExceptionError',
                    // Thrown in filterListSetup and getPredefinedFilterById
                    'SugarApiExceptionNotAuthorized',
                    // Thrown in filterListSetup
                    'SugarApiExceptionInvalidParameter',
                ],
            ],
            'filterModulePost' => [
                'reqType' => 'POST',
                'path' => ['Dashboards', 'filter'],
                'pathVars' => ['module', ''],
                'method' => 'filterList',
                'shortHelp' => 'Lists filtered records.',
                'longHelp' => 'include/api/help/module_filter_post_help.html',
                'exceptions' => [
                    // Thrown in getPredefinedFilterById
                    'SugarApiExceptionNotFound',
                    'SugarApiExceptionError',
                    // Thrown in filterList and filterListSetup
                    'SugarApiExceptionInvalidParameter',
                    // Thrown in filterListSetup, getPredefinedFilterById, and parseArguments
                    'SugarApiExceptionNotAuthorized',
                ],
            ],
            'filterModulePostCount' => [
                'reqType' => 'POST',
                'path' => ['Dashboards', 'filter', 'count'],
                'pathVars' => ['module', '', ''],
                'method' => 'filterListCount',
                'shortHelp' => 'Lists filtered records.',
                'longHelp' => 'include/api/help/module_filter_post_help.html',
                'exceptions' => [
                    // Thrown in getPredefinedFilterById
                    'SugarApiExceptionNotFound',
                    'SugarApiExceptionError',
                    // Thrown in filterListSetup and getPredefinedFilterById
                    'SugarApiExceptionNotAuthorized',
                    // Thrown in filterListSetup
                    'SugarApiExceptionInvalidParameter',
                ],
            ],
            'filterModuleCount' => [
                'reqType' => 'GET',
                'path' => ['Dashboards', 'filter', 'count'],
                'pathVars' => ['module', '', ''],
                'method' => 'getFilterListCount',
                'shortHelp' => 'Lists filtered records.',
                'longHelp' => 'include/api/help/module_filter_post_help.html',
                'exceptions' => [
                    // Thrown in getPredefinedFilterById
                    'SugarApiExceptionNotFound',
                    'SugarApiExceptionError',
                    // Thrown in filterListSetup
                    'SugarApiExceptionNotAuthorized',
                    'SugarApiExceptionInvalidParameter',
                ],
            ],
            'filterModuleSum' => [
                'reqType' => 'GET',
                'path' => ['Dashboards', 'total'],
                'pathVars' => ['module', '', ''],
                'method' => 'getFilterListSum',
                'shortHelp' => 'Lists field sum by filtered records.',
                'longHelp' => 'include/api/help/module_sum_by_filter_get_help.html',
                'exceptions' => [
                    // Thrown in getPredefinedFilterById
                    'SugarApiExceptionNotFound',
                    'SugarApiExceptionError',
                    // Thrown in filterListSetup
                    'SugarApiExceptionNotAuthorized',
                    'SugarApiExceptionInvalidParameter',
                ],
                'minVersion' => '11.21',
            ],
        ];
    }

    /**
     * @inheritDoc
     */
    protected function addFilterByLicense(SugarQuery $query): void
    {
        $acm = AccessControlManager::instance();

        $marketModules = ['sf_Dialogs', 'sf_webActivity', 'sf_EventManagement', 'sf_WebActivityDetail'];
        $marketModules = array_filter($marketModules, function ($module) use ($acm) {
            return !$acm->allowModuleAccess($module);
        });

        if (count($marketModules)) {
            self::addFilter('dashboard_module', ['$not_in' => $marketModules], $query->where(), $query);
        }
    }

    /**
     * @inheridDoc
     */
    public function filterListSetup(ServiceBase $api, array $args, $acl = 'list')
    {
        if (empty($args['filter'][0])) {
            return parent::filterListSetup($api, $args, $acl);
        }

        if (isset($args['filter'][0]['$and'])) {
            $searchTerm = '';
            foreach ($args['filter'][0]['$and'] as $filter) {
                if (isset($filter['name']['$starts'])) {
                    $searchTerm = $filter['name']['$starts'];
                    break;
                }
            }
        } else {
            $searchTerm = $args['filter'][0]['name']['$starts'] ?? '';
        }

        global $locale;
        $currentUserLanguage = $locale->getAuthenticatedUserLanguage();
        $query = "SELECT id, name, dashboard_module FROM dashboards";
        $db = DBManagerFactory::getInstance();
        $result = $db->query($query);

        $dashboards = [];

        while ($row = $db->fetchByAssoc($result)) {
            $dashboards[$row['id']] = [
                'name' => $row['name'],
                'module' => $row['dashboard_module'],
            ];
        }

        $dashboard_names = [];

        foreach ($dashboards as $dashboardId => $dashboard) {
            $lbl = $dashboard['name'];
            $module_lang = return_module_language($currentUserLanguage, $dashboard['module']);
            $app_lang = return_application_language($currentUserLanguage);

            $dashboardName = $module_lang[$lbl] ?? $app_lang[$lbl] ?? $lbl;

            if (!empty($dashboardName)) {
                $dashboardLbl = strtolower($dashboardName);
                $searchTerm = strtolower($searchTerm);

                if (mb_strstr($dashboardLbl, $searchTerm) !== false) {
                    $dashboard_names[$dashboardId] = $dashboardName;
                }
            }
        }

        [$args, $query, $options, $seed] = parent::filterListSetup($api, $args, $acl);

        $unionQuery = self::getQueryObject($seed, $options);
        $unionQuery->where = null;

        // The union_tmp table is used in SugarQuery_Compiler_Doctrine when compiling unions with that contains limits
        $unionQuery->orderBy('union_tmp.date_modified', 'DESC');
        $unionQuery->limit = $query->limit;
        $unionQuery->offset = $query->offset;

        $query->limit = null;
        $query->order_by = [];
        $query->offset = 0;

        $unionQuery->union($query, false);

        if (!empty($searchTerm) && !empty($dashboard_names)) {
            $idsQuery = self::getQueryObject($seed, $options);

            $idsQuery->select = $query->select;
            $unionQuery->join = $query->join;
            $idsQuery->where = null;
            $idsQuery->limit = null;
            $idsQuery->order_by = [];
            $idsQuery->offset = 0;

            self::addFilter('id', ['$in' => array_keys($dashboard_names)], $idsQuery->where(), $idsQuery);

            if (isset($args['filter'][0]['$and'])) {
                foreach ($args['filter'][0]['$and'] as $filter) {
                    if (isset($filter['name']['$starts'])) {
                        continue;
                    }

                    $field = key($filter);
                    $condition = $filter[$field];

                    self::addFilter($field, $condition, $idsQuery->where(), $idsQuery);
                }
            }

            $unionQuery->union($idsQuery, false);
            $unionQuery->limit = null;
        }

        $options['id_query'] = $unionQuery;

        return [$args, $unionQuery, $options, $seed];
    }
}
