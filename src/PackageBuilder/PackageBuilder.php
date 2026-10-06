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
namespace Sugarcrm\Sugarcrm\PackageBuilder;

use Sugarcrm\Sugarcrm\Util\Files\FileLoader;

class PackageBuilder
{
    use FileHandlerTrait;

    const DEFAULT_LANG = 'en_us';
    const ROLE_FILTERS_PATH = 'custom/Extension/application/Ext/DropdownFilters/roles';
    const CUSTOM_REL_LABEL = 'PackageBuilderCustomRelLabel';
    const CUSTOM_FIELD_LABEL = 'PackageBuilderCustomFieldLabel';

    /**
     * Extract customizations in one or multiple categories
     * @param array $categories
     * @return array
     */
    public function extract(array $categories): array
    {
        $customizationsMap = [];

        foreach ($categories as $category) {
            if (!is_string($category)) {
                throw new \SugarApiExceptionInvalidParameter('Category should be a string');
            }
            $functionName = 'process'.str_replace('_', '', ucwords($category, '_'));
            if (method_exists($this, $functionName)) {
                if ($category === 'layouts') {
                    // also return search_layouts
                    $customizationsMap = $this->$functionName();
                } else {
                    $customizationsMap[$category] = $this->$functionName();
                }
            }
        }

        return $customizationsMap;
    }

    /**
     * Extract customizations for category 'miscellaneous'
     * @return array
     */
    protected function processMiscellaneous(): array
    {
        $mapByElement = [
            'scheduled_jobs' => $this->processScheduledJobs(),
            'display_modules_and_subpanels' => $this->processDisplayModulesAndSubpanels(),
            'quick_create_bar' => $this->processQuickCreateBar(),
        ];
        return $mapByElement;
    }

    /**
     * Extract customizations for category 'fields'
     * @return array
     */
    protected function processFields(): array
    {
        global $db;

        $loadedLanguages = [];
        $customFieldsData  = [];
        $mapByElement = [];
        // Get custom fields from fields_meta_data
        $qb = $db->getConnection()->createQueryBuilder();
        $qb->select('*')
            ->from('fields_meta_data')
            ->where('deleted = 0')
            ->addOrderBy('custom_module', 'ASC')
            ->addOrderBy('name', 'ASC')
            ->addOrderBy('id', 'ASC');
        $stmt = $qb->executeQuery();
        while ($row = $stmt->fetchAssociative()) {
            $customFieldsData[] = $row;

            if (isset($loadedLanguages[$row['custom_module']]) || empty($row['custom_module'])) {
                continue;
            }

            $loadedLanguages[$row['custom_module']] = return_module_language(self::DEFAULT_LANG, $row['custom_module']);
        }

        // Insert base fields foreach module
        $moduleList = \MetaDataManager::getManager()->getModuleList();
        foreach ($moduleList as $moduleName) {
            $bean = \BeanFactory::newBean($moduleName);

            if ($bean instanceof \SugarBean === false) {
                continue;
            }

            // Get base fields
            $baseFields = array_diff_key($bean->getFieldDefinitions(), $bean->getFieldDefinitions(
                'source',
                ['custom_fields']// source: custom_fields were already retrieved abouve from fields_meta_data
            ));

            // Insert base fields in to $customFieldsData
            foreach ($baseFields as $field) {
                // For actionbuttons skip, as these are retrived from fields_meta_data
                if ($field['type'] === 'actionbutton') {
                    continue;
                }

                $field['custom_module'] = $moduleName;

                // Later these base fields will be checked for customizations to be extracted
                array_push($customFieldsData, $field);
            }
        }

        foreach ($customFieldsData as $index => $fieldData) {
            $elementMap = [
                'files' => [],
                'db' => [
                    'fields_meta_data' => [$fieldData],
                ],
            ];

            $fieldCustomModule = $fieldData['custom_module'];
            $vardefFileName = 'sugarfield_' . $fieldData['name'];

            $vardefFileTempPath = 'custom/Extension/modules/' . $fieldCustomModule . '/Ext/Vardefs';
            $vardefFilePath = $vardefFileTempPath . '/' . $vardefFileName . '.php';

            if (\SugarAutoLoader::fileExists($vardefFilePath)) {
                $elementData = [
                    'Name' => $fieldData['name'] ?? '',
                    'Type' => $fieldData['type'] ?? '',
                    'Custom Module' => $fieldData['custom_module'] ?? '',
                    'Date Modified' => $fieldData['date_modified'] ?? '',
                ];
                $elementMap['files'][] = $this->getFileData($vardefFilePath);
                $customLabels = $this->createFieldCustomLabels($fieldData['name'], $fieldCustomModule);
                $elementMap['files'] = array_merge($elementMap['files'], $customLabels);

                switch ($fieldData['type']) {
                    case 'currency':
                        $fieldDef = FileLoader::varFromInclude($vardefFilePath, 'dictionary');
                        if ($fieldDef !== null) {
                            $beanDef = \BeanFactory::getDefinition($fieldData['custom_module']);
                            if ($beanDef instanceof \SugarBean) {
                                $beanObjName = $beanDef->getObjectName();
                                $elementMap['related_fields'] = $fieldDef[$beanObjName]['fields'][$fieldData['name']]['related_fields'];
                            }
                        }
                        break;
                    case 'enum':
                        $dropdownList = !empty($fieldData['ext1']) ? $fieldData['ext1'] : '';
                        if (empty($dropdownList)) {
                            $beanDef = \BeanFactory::getDefinition($fieldData['custom_module']);
                            $beanFieldDef = $beanDef->getFieldDefinition($fieldData['name']);
                            if ($beanFieldDef !== false) {
                                $dropdownList = isset($beanFieldDef['options']) ? $beanFieldDef['options'] : '';
                            }
                        }
                        if (is_string($dropdownList)) {
                            $elementMap['related_dropdown'] = $dropdownList;
                        }
                        break;
                }

                $mapByElement[] = ['data' => $elementData, 'map' => $elementMap];
            }
        }

        return $mapByElement;
    }

    /**
     * Get field definition.
     * @param string $moduleName
     * @param string $fieldName
     * @return array
     */
    protected function getFieldDefinition(string $moduleName, string $fieldName): array
    {
        $beanDef = \BeanFactory::getDefinition($moduleName);
        $fieldDef = $beanDef->getFieldDefinition($fieldName);
        return $fieldDef;
    }

    /**
     * Create custom field labels.
     * @param string $fieldName
     * @param string $moduleName
     * @return array
     */
    protected function createFieldCustomLabels(string $fieldName, string $moduleName): array
    {
        $files = [];
        $fieldDef = $this->getFieldDefinition($moduleName, $fieldName);
        $langLbl = $fieldDef['vname'] ?? '';
        $langDir = 'custom/modules/' . $moduleName . '/Ext/Language';
        if (!empty($langLbl) && is_dir($langDir)) {
            foreach (scandir($langDir) as $langFile) {
                if (!str_ends_with($langFile, '.lang.ext.php')) {
                    continue;
                }
                $mod_strings = [];
                include FileLoader::validateFilePath("$langDir/$langFile");
                if (isset($mod_strings[$langLbl])) {
                    $content = "<?php \n // created: " . date('Y-m-d H:i:s') . "\n";
                    $content .= override_value_to_string('mod_strings', $langLbl, $mod_strings[$langLbl]) . "\n";
                    $fileName = str_replace('lang.ext', self::CUSTOM_FIELD_LABEL . '_' . $fieldName, $langFile);
                    $filePath = "custom/Extension/modules/$moduleName/Ext/Language/$fileName";
                    $files[] = [
                        'path' => $filePath,
                        'content' => base64_encode($content),
                    ];
                }
            }
        }
        return $files;
    }

    /**
     * Process customizations for category 'relationships'
     * @return array
     */
    protected function processRelationships(): array
    {
        global $dictionary;
        $mapByElement = [];

        if (\SugarAutoLoader::fileExists('custom/metadata')) {
            $dirFiles = \SugarAutoLoader::getDirFiles('custom/metadata');
            foreach ($dirFiles as $filePath) {
                $fileName = basename($filePath);
                if ($fileName) {
                    $relationshipName = explode('MetaData.php', $fileName)[0];
                    $filePath = getcwd() . '/custom/metadata/' . $fileName;

                    include $filePath;

                    if (empty($dictionary[$relationshipName]['relationships'][$relationshipName])) {
                        continue;
                    }

                    $relationshipType = $dictionary[$relationshipName]['true_relationship_type'];
                    $leftModule = $dictionary[$relationshipName]['relationships'][$relationshipName]['lhs_module'];
                    $rightModule = $dictionary[$relationshipName]['relationships'][$relationshipName]['rhs_module'];

                    $elementMap = ['files' => [], 'db' => []];

                    $elementData = [
                        'Name' => $relationshipName,
                        'Type' => $relationshipType,
                        'Left Module' => $leftModule,
                        'Right Module' => $rightModule,
                        'From Studio' => true,
                    ];

                    $metadataFilePath = 'custom/metadata/' . $fileName;
                    $this->addFileData($metadataFilePath, $elementMap);

                    $tableDictionaryFilePath = 'custom/Extension/application/Ext/TableDictionary/' . $relationshipName . '.php';
                    $this->addFileData($tableDictionaryFilePath, $elementMap);

                    $modulesInvolved = [
                        $leftModule,
                        $rightModule,
                    ];

                    foreach ($modulesInvolved as $key => $moduleName) {
                        $languageFilePath = 'custom/Extension/modules/' . $moduleName . '/Ext/Language/' . 'en_us.custom' . $relationshipName . '.php';
                        $this->addFileData($languageFilePath, $elementMap);

                        $vardefFilePath = 'custom/Extension/modules/' . $moduleName . '/Ext/Vardefs/' . $relationshipName . '_' . $moduleName . '.php';
                        $this->addFileData($vardefFilePath, $elementMap);

                        $layoutdefFilePath = 'custom/Extension/modules/' . $moduleName . '/Ext/Layoutdefs/' . $relationshipName . '_' . $moduleName . '.php';
                        $this->addFileData($layoutdefFilePath, $elementMap);

                        $subpanelFilePath = 'custom/Extension/modules/' . $moduleName . '/Ext/clients/base/layouts/subpanels/' . $relationshipName . '_' . $moduleName . '.php';
                        $this->addFileData($subpanelFilePath, $elementMap);

                        $vardefFilePath = 'custom/Extension/modules/' . $moduleName . '/Ext/Vardefs/sugarfield_' . $relationshipName . '_name' . '.php';
                        $this->addFileData($vardefFilePath, $elementMap);

                        $customRelationshipLabel = $this->createRelationshipsCustomLabels($relationshipName, $moduleName);
                        if ($customRelationshipLabel) {
                            $vardefFilePath = 'custom/Extension/modules/' . $moduleName . '/Ext/Language/en_us.' . self::CUSTOM_REL_LABEL .
                                '_' . $relationshipName . '_' . $moduleName . '.php';

                            $fileData = [
                                'path' => $vardefFilePath,
                                'content' => base64_encode($customRelationshipLabel),
                            ];
                            $elementMap['files'][] = $fileData;
                        }
                    }
                    $mapByElement[] = ['data' => $elementData, 'map' => $elementMap];
                }
            }
        }

        return $mapByElement;
    }

    /**
     * Create custom labels for relationships
     * @param string $relationshipName
     * @param string $moduleName
     * @return string
     */
    protected function createRelationshipsCustomLabels(string $relationshipName, string $moduleName): string
    {
        $relationshipName = strtoupper($relationshipName);
        global $current_language;

        $modStringLeft = strtoupper('LBL_' . $relationshipName . '_FROM_' . $moduleName . '_TITLE');

        $content = '';

        if (isset(return_module_language($current_language, $moduleName)[$modStringLeft])) {
            $content = '<?php' . PHP_EOL . PHP_EOL;
            $content .= '$mod_strings[\'' . $modStringLeft . '\'] = ' .
                var_export(return_module_language($current_language, $moduleName)[$modStringLeft], true) . ';' . PHP_EOL;
        }

        return $content;
    }

    /**
     * Process customizations for category 'layouts'
     * @return array
     */
    protected function processLayouts(): array
    {
        $mapByElement = [
            'layouts' => [],
            'search_layouts' => [],
        ];

        // Process for custom layouts
        $dirFiles = \SugarAutoLoader::scanDir('custom/modules');
        $extensionDirFiles = \SugarAutoLoader::scanDir('custom/Extension/modules');

        $modulesList = array_unique(array_merge(array_keys($dirFiles), array_keys($extensionDirFiles)));
        $modulesListDictionary = array_combine(array_map('strtolower', $modulesList), $modulesList);
        foreach ($dirFiles as $folderName => $folderContent) {
            if ($folderContent != 1) { //is directory
                $moduleName = $folderName;

                // Get customizations from clients folder
                if (\SugarAutoLoader::fileExists('custom/modules/' . $moduleName . '/clients')) {
                    $currentClientsDirFiles = \SugarAutoLoader::scanDir('custom/modules/' . $moduleName . '/clients');
                    foreach ($currentClientsDirFiles as $clientFolderName => $clientFolderContent) {
                        if ($clientFolderContent != 1) { //is directory
                            $clientName = $clientFolderName;

                            // Add Layouts
                            if (\SugarAutoLoader::fileExists('custom/modules/' . $moduleName . '/clients/' . $clientName . '/views')) {
                                $currentClientDirViewsFiles = \SugarAutoLoader::scanDir('custom/modules/' . $moduleName . '/clients/' . $clientName . '/views');
                                foreach ($currentClientDirViewsFiles as $currentViewFolderName => $currentViewFolderContent) {
                                    if ($currentViewFolderContent != 1) { //is directory
                                        $viewName = $currentViewFolderName;

                                        $viewFilePath = 'custom/modules/' . $moduleName . '/clients/' . $clientName . '/views/' . $viewName . '/' . $viewName . '.php';
                                        if (\SugarAutoLoader::fileExists($viewFilePath)) {
                                            $elementMap = ['files' => [], 'db' => []];

                                            $elementData = [
                                                'Module' => $moduleName,
                                                'Client' => $clientName,
                                                'View' => $viewName,
                                            ];

                                            $elementMap['files'][] = $this->getFileData($viewFilePath);

                                            if (preg_match('/subpanel-for-/', $viewName)) {
                                                $relation = implode('-', array_slice(explode('-', $viewName), -2));
                                                $relatedToModuleNameKey = explode('-', $relation)[0];
                                                $overrideFilePath = 'custom/Extension/modules/' . $modulesListDictionary[$relatedToModuleNameKey] .
                                                    '/Ext/clients/' . $clientName . '/layouts/subpanels/_overridesubpanel-for-' . $relation . '.php';

                                                if (\SugarAutoLoader::fileExists($overrideFilePath)) {
                                                    $elementMap['files'][] = $this->getFileData($overrideFilePath);
                                                }
                                            }

                                            $mapByElement['layouts'][] = [
                                                'data' => $elementData,
                                                'map' => $elementMap,
                                            ];
                                        }

                                        if (\SugarAutoLoader::fileExists('custom/modules/' . $moduleName . '/clients/' . $clientName . '/views/' . $viewName . '/roles')) {
                                            $currentClientDirViewsRolesFiles = \SugarAutoLoader::scanDir('custom/modules/' . $moduleName . '/clients/' . $clientName . '/views/' . $viewName . '/roles');
                                            foreach ($currentClientDirViewsRolesFiles as $currentClientDirViewsRolesId => $currentClientDirViewsRolesContent) {
                                                if ($currentClientDirViewsRolesContent != -1) {
                                                    $viewRoleFilePath = 'custom/modules/' . $moduleName . '/clients/' . $clientName . '/views/' . $viewName . '/roles/' . $currentClientDirViewsRolesId . '/' . $viewName . '.php';

                                                    if (\SugarAutoLoader::fileExists($viewRoleFilePath)) {
                                                        $roleFileData = $this->getFileData($viewRoleFilePath);

                                                        foreach ($mapByElement['layouts'] as $key => $value) {
                                                            if ($value['data']['Module'] == $moduleName &&
                                                                $value['data']['Client'] == $clientName &&
                                                                $value['data']['View'] == $viewName) {
                                                                $mapByElement['layouts'][$key]['map']['files'][] = $roleFileData;
                                                                break;
                                                            }
                                                        }
                                                    }
                                                }
                                            }
                                        }
                                    }
                                }
                            }

                            // Add Search Layouts
                            $searchFieldsPath = 'custom/modules/' . $moduleName . '/clients/' . $clientName .
                                '/filters/default/default.php';
                            if (\SugarAutoLoader::fileExists($searchFieldsPath)) {
                                $elementMap = ['files' => [], 'db' => []];

                                $elementData = [
                                    'Module' => $moduleName,
                                    'Client' => $clientName,
                                    'Filters' => 'default',
                                ];

                                $elementMap['files'][] = $this->getFileData($searchFieldsPath);

                                $mapByElement['search_layouts'][] = [
                                    'data' => $elementData,
                                    'map' => $elementMap,
                                ];
                            }
                        }
                    }
                }

                // For Users add detailview and editview customizations
                if ($moduleName === 'Users') {
                    $usersBwcViews = [
                        'detailviewdefs',
                        'editviewdefs',
                    ];

                    foreach ($usersBwcViews as $viewName) {
                        // Build file path
                        $viewFilePath = 'custom/modules/Users/metadata/' . $viewName . '.php';

                        // If custom bwc view exists, map the element
                        if (\SugarAutoLoader::fileExists($viewFilePath)) {
                            // Map element
                            $elementMap = ['files' => [], 'db' => []];

                            $elementData =[
                                'Module' => $moduleName,
                                'Client' => 'metadata',
                                'View' => $viewName,
                            ];

                            $elementMap['files'][] = $this->getFileData($viewFilePath);
                            $mapByElement['layouts'][] = [
                                'data' => $elementData,
                                'map' => $elementMap,
                            ];
                        }
                    }
                }
            }
        }

        return $mapByElement;
    }

    /**
     * Process customizations for category 'dropdowns'
     * @return array
     */
    protected function processDropdowns(): array
    {
        $mapByElement = [];
        $dropdownList = [];

        $dirFilesScan = \SugarAutoLoader::scanDir('custom/Extension/application/Ext/Language');
        $dirFiles = [];
        foreach ($dirFilesScan as $fileName => $fileContent) {
            if ($fileContent == 1) {
                $dirFiles[] = $fileName;
            }
        }

        foreach ($dirFiles as $filePath) {
            $fileName = basename($filePath);
            if ($fileName) {
                if (strpos($fileName, '.sugar_') !== false) {
                    $dropdownName = explode('.php', explode('.sugar_', $fileName)[1])[0];

                    $elementData = ['Dropdown Name' => $dropdownName];

                    if (!isset($dropdownList[$dropdownName])) {
                        $dropdownList[$dropdownName] = $dropdownName;

                        $elementMap = ['files' => [], 'db' => []];
                        $mapByElement[] = ['data' => $elementData, 'map' => $elementMap];
                    }

                    $languageFilePath = 'custom/Extension/application/Ext/Language/' . $fileName;
                    $fileData = $this->getFileData($languageFilePath);

                    foreach ($mapByElement as $key => $value) {
                        if ($value['data']['Dropdown Name'] === $elementData['Dropdown Name']) {
                            $mapByElement[$key]['map']['files'][] = $fileData;
                            break;
                        }
                    }
                }
            }
        }

        // Get used roles (role configurations for dropdowns)
        $roles = \SugarAutoLoader::scanDir(self::ROLE_FILTERS_PATH);

        // If no roles found, return the dropdowns without roles
        if (empty($roles)) {
            return $mapByElement;
        }

        $entries = [];
        // Get the configuration for each dropdown based on the roles found
        foreach ($mapByElement as $key => $entry) {
            $entries[] = $entry;
            foreach ($roles as $roleId => $roleFiles) {
                $dropdownFileName = $entry['data']['Dropdown Name'] . '.php';

                // Check if the current role has a configuration for the current entry/dropdown
                if (isset($roleFiles[$dropdownFileName])) {
                    $elementData = $entry['data'];
                    $elementData['Role'] = $roleId;
                    $elementMap = ['files' => [], 'db' => []];

                    // Path for the role configuration
                    $dropdownRolePath = self::ROLE_FILTERS_PATH . "/{$roleId}/{$dropdownFileName}";

                    // Build role filter data for the current entry/dropdown
                    $elementMap['files'][] = $this->getFileData($dropdownRolePath);
                    $entries[] = ['data' => $elementData, 'map' => $elementMap];
                }
            }
        }

        return $entries;
    }

    /**
     * Extract customizations for category 'dashboards'
     * @return array
     * @throws \Doctrine\DBAL\Exception
     * @throws \Doctrine\DBAL\Driver\Exception
     */
    protected function processDashboards(): array
    {
        global $db;
        $mapByElement = [];

        $qb = $db->getConnection()->createQueryBuilder();
        $fields = ['d.id', 'name', 'dashboard_module', 'view_name', 'default_dashboard', 'assigned_user_id', 'team_id', 'd.date_modified'];
        $qb->select($fields)
            ->from('dashboards', 'd')
            ->join('d', 'users', 'u', 'd.assigned_user_id=u.id')
            ->where('d.deleted = 0');
        $stmt = $qb->executeQuery();
        $rows = $stmt->fetchAllAssociative();
        // use cache to reduce db requests for instances with big amount of dashboards
        $teams = $users = [];
        foreach ($rows as $row) {
            $teamId = $row['team_id'];
            $userId = $row['assigned_user_id'];
            $teamName = $teams[$teamId] ?? $teams[$teamId] = \BeanFactory::retrieveBean('Teams', $teamId)->name;
            $userName = $users[$userId] ?? $users[$userId] = \BeanFactory::retrieveBean('Users', $userId)->name;
            $this->addDashboardDataToMap(
                $row,
                $mapByElement,
                $teamName,
                $userName
            );
        }

        return $mapByElement;
    }

    /**
     * Add dashboard data to map
     * @param array $row
     * @param array $mapByElement
     * @param string $teamName
     * @param string $userName
     */
    protected function addDashboardDataToMap($row, &$mapByElement, $teamName, $userName): void
    {
        $elementMap = [
            'files' => [],
            'db' => [
                'dashboards' => [],
            ],
        ];

        $row['name']  = translate($row['name'], $row['dashboard_module']);
        $row['team_id'] = $teamName ?? '';
        $row['assigned_user_id'] = $userName ?? '';

        $elementData = $row;
        $elementMap['id'] = $row['id'];
        $mapByElement[] = ['data' => $elementData, 'map' => $elementMap];
        unset($elementData, $elementMap);
    }

    /**
     * Extract customizations for category 'reports'
     * @return array
     */
    protected function processReports(): array
    {
        $mapByElement = [];
        global $db;
        $qb = $db->getConnection()->createQueryBuilder();
        $qb->select('*')
            ->from('saved_reports')
            ->where('deleted = 0');
        $stmt = $qb->executeQuery();

        // Build data for each workflow entry
        while ($row = $stmt->fetchAssociative()) {
            $elementMap = [
                'files' => [],
                'db' => [
                    'saved_reports' => [
                        $row,
                    ],
                ],
            ];

            $elementData = [
                'Report Name' => $row['name'],
                'Module' => $row['module'],
                'Report Type' => $row['report_type'],
            ];

            $mapByElement[] = ['data' => $elementData, 'map' => $elementMap];
        }

        return $mapByElement;
    }

    /**
     * Extract customizations for category 'workflows'
     * @return array
     */
    protected function processWorkflows(): array
    {
        global $db;
        // Create final array with all the entries found
        $mapByElement = [];

        // Select all the workflows that are not sub-workflows (parent id not set)
        $qb = $db->getConnection()->createQueryBuilder();
        $qb->select('*')
            ->from('workflow')
            ->where('deleted = 0')
            ->andWhere('parent_id IS NULL');
        $stmt = $qb->executeQuery();

        // Build data for each workflow entry
        while ($row = $stmt->fetchAssociative()) {
            $mapByElement[] = $this->buildWorkflowEntry($row);
        }

        return $mapByElement;
    }

    /**
     * Build workflow entry
     * @param array $row
     * @return array
     */
    protected function buildWorkflowEntry(array $row): array
    {
        global $db;

        // Create entry parts
        $map = ['db' => [], 'files' => []];
        $data = [
            'Name' => $row['name'],
            'Base Module' => $row['base_module'],
        ];

        // Fill in workflow table with parent workflow
        $map['db']['workflow'] = [];
        $map['db']['workflow'][] = $row;

        $workflowsIds = [
            $row['id'], // parent workflow
        ];

        $subQuery = $db->getConnection()->createQueryBuilder();
        $expr = $subQuery->expr();
        $subQuery->select('id')
            ->from('workflow_actionshells')
            ->where($expr->eq('parent_id', $expr->literal($row['id'])));
        $qb = $db->getConnection()->createQueryBuilder();
        $qb->select('*')
            ->from('workflow')
            ->where('parent_id IN (' . $subQuery->getSQL() . ')')
            ->andWhere('deleted = 0');
        $stmt = $qb->executeQuery();

        while ($childWorkflow = $stmt->fetchAssociative()) {
            $workflowsIds[] = $childWorkflow['id'];
            $map['db']['workflow'][] = $childWorkflow; // Fill in workflow table with child workflows
        }

        //workflow_actions table
        $map['db']['workflow_actions'] = [];
        $qb = $db->getConnection()->createQueryBuilder();
        $subQuery = $db->getConnection()->createQueryBuilder();
        $subQuery->select('id')
            ->from('workflow_actionshells')
            ->where($subQuery->expr()->in(
                'parent_id',
                $qb->createPositionalParameter($workflowsIds, \Doctrine\DBAL\Connection::PARAM_STR_ARRAY)
            ))
            ->andWhere('deleted = 0');
        $qb->select('*')
            ->from('workflow_actions')
            ->where('parent_id IN (' . $subQuery->getSQL() . ')')
            ->andWhere('deleted = 0');
        $stmt = $qb->executeQuery();

        while ($worfkflowAction = $stmt->fetchAssociative()) {
            $map['db']['workflow_actions'][] = $worfkflowAction;
        }

        //workflow_actionshells table
        $map['db']['workflow_actionshells'] = [];
        $qb = $db->getConnection()->createQueryBuilder();
        $qb->select('*')
            ->from('workflow_actionshells')
            ->where($qb->expr()->in(
                'parent_id',
                $qb->createPositionalParameter($workflowsIds, \Doctrine\DBAL\Connection::PARAM_STR_ARRAY)
            ))
            ->andWhere('deleted = 0');
        $stmt = $qb->executeQuery();

        while ($workflowActionShell = $stmt->fetchAssociative()) {
            $map['db']['workflow_actionshells'][] = $workflowActionShell;
        }

        //workflow_alerts table
        $map['db']['workflow_alerts'] = [];
        $qb = $db->getConnection()->createQueryBuilder();
        $subQuery = $db->getConnection()->createQueryBuilder();
        $subQuery->select('id')
            ->from('workflow_alertshells')
            ->where($subQuery->expr()->in(
                'parent_id',
                $qb->createPositionalParameter($workflowsIds, \Doctrine\DBAL\Connection::PARAM_STR_ARRAY)
            ));
        $qb->select('*')
            ->from('workflow_alerts')
            ->where('parent_id IN (' . $subQuery->getSQL() . ')')
            ->andWhere('deleted = 0');
        $stmt = $qb->executeQuery();

        while ($workflowAlert = $stmt->fetchAssociative()) {
            $map['db']['workflow_alerts'][] = $workflowAlert;
        }

        //workflow_alertshells table
        $map['db']['workflow_alertshells'] = [];
        $qb = $db->getConnection()->createQueryBuilder();
        $qb->select('*')
            ->from('workflow_alertshells')
            ->where($qb->expr()->in(
                'parent_id',
                $qb->createPositionalParameter($workflowsIds, \Doctrine\DBAL\Connection::PARAM_STR_ARRAY)
            ))
            ->andWhere('deleted = 0');
        $stmt = $qb->executeQuery();

        while ($workflowAlertShell = $stmt->fetchAssociative()) {
            $map['db']['workflow_alertshells'][] = $workflowAlertShell;
        }

        //workflow_schedules table
        $map['db']['workflow_schedules'] = [];
        $qb = $db->getConnection()->createQueryBuilder();
        $qb->select('*')
            ->from('workflow_schedules')
            ->where($qb->expr()->in(
                'workflow_id',
                $qb->createPositionalParameter($workflowsIds, \Doctrine\DBAL\Connection::PARAM_STR_ARRAY)
            ))
            ->andWhere('deleted = 0');
        $stmt = $qb->executeQuery();

        while ($rowC = $stmt->fetchAssociative()) {
            $map['db']['workflow_schedules'][] = $rowC;
        }

        //workflow_triggershells table
        $map['db']['workflow_triggershells'] = [];
        $map['db']['expressions'] = []; // workflow_triggershells come with expressions
        $qb = $db->getConnection()->createQueryBuilder();
        $qb->select('*')
            ->from('workflow_triggershells')
            ->where($qb->expr()->in(
                'parent_id',
                $qb->createPositionalParameter($workflowsIds, \Doctrine\DBAL\Connection::PARAM_STR_ARRAY)
            ))
            ->andWhere('deleted = 0');
        $stmt = $qb->executeQuery();

        while ($rowC = $stmt->fetchAssociative()) {
            $map['db']['workflow_triggershells'][] = $rowC;

            // expressions table
            $triggerId = $rowC['id'];

            // Select all the expressions related this current workflow_triggershell
            $qb = $db->getConnection()->createQueryBuilder();
            $qb->select('*')
                ->from('expressions')
                ->where($qb->expr()->eq('parent_id', $qb->expr()->literal($triggerId)))
                ->andWhere('deleted = 0');
            $stmt = $qb->executeQuery();

            while ($expression = $stmt->fetchAssociative()) {
                $map['db']['expressions'][] = $expression;
            }
        }

        return ['data' => $data, 'map' => $map];
    }

    /**
     * Extract customizations for category 'advanced_workflows'
     * @return array
     */
    protected function processAdvancedWorkflows(): array
    {
        global $db;
        $mapByElement = [];

        $qb = $db->getConnection()->createQueryBuilder();
        $qb->select('*')
            ->from('pmse_project')
            ->where('deleted = 0');
        $stmt = $qb->executeQuery();

        while ($row = $stmt->fetchAssociative()) {
            $map = ['db' => [], 'files' => []];
            $data = [
                'Name' => $row['name'],
                'Description' => $row['description'],
                'Module' => $row['prj_module'],
                'Status' => $row['prj_status'],
            ];

            $map['db']['pmse_project']   = [];
            $map['db']['pmse_project'][] = $row;

            $mapByElement[] = ['data' => $data, 'map' => $map];
        }

        return $mapByElement;
    }

    /**
     * Extract customizations for category 'scheduled_jobs'
     * @return array
     */
    protected function processScheduledJobs(): array
    {
        global $db;
        $map = ['files' => [], 'db' => []];
        $databaseRows = [];

        $qb = $db->getConnection()->createQueryBuilder();
        $qb->select('*')
            ->from('schedulers')
            ->where('deleted = 0');
        $stmt = $qb->executeQuery();

        while ($row = $stmt->fetchAssociative()) {
            $databaseRows[] = $row;
        }

        $map['db']['schedulers'] = $databaseRows;
        $data = ['Category' => 'Scheduled Jobs', 'Description' => 'Scheduled Jobs added via Administration'];
        return ['map' => $map, 'data' => $data];
    }

    /**
     * Extract customizations for category 'display_modules_and_subpanels'
     * @return array
     */
    protected function processDisplayModulesAndSubpanels(): array
    {
        global $db;
        $map = ['files' => [], 'db' => []];
        $databaseRows = [];

        $qb = $db->getConnection()->createQueryBuilder();
        $qb->select('*')
            ->from('config')
            ->where($qb->expr()->eq('category', $qb->expr()->literal('MySettings')));
        $stmt = $qb->executeQuery();

        while ($row = $stmt->fetchAssociative()) {
            $databaseRows[] = $row;
        }

        $map['db']['config'] = $databaseRows;
        $data = [
            'Category' => 'Display Modules and Subpanels',
            'Description' => 'Settings displayed in Administration panel',
        ];
        return ['map' => $map, 'data' => $data];
    }

    /**
     * Extract customizations for category 'quick_create_bar'
     * @return array
     */
    protected function processQuickCreateBar(): array
    {
        $map = ['files' => [],'db' => []];

        $dirFiles = \SugarAutoLoader::scanDir('custom/modules');
        foreach ($dirFiles as $folderName => $folderContent) {
            $moduleName = $folderName;

            if ($folderContent != 1) {
                $quickcreateFilePath = 'custom/modules/' . $moduleName . '/clients/base/menus/quickcreate/quickcreate.php';
                $this->addFileData($quickcreateFilePath, $map);
            }
        }
        $data = [
            'Category' => 'Quick Create Bar',
            'Description' => 'Quick Create Bar from Sugar Header',
        ];
        return ['map' => $map, 'data' => $data];
    }

    /**
     * Extract customizations for category 'acl'
     * @return array
     */
    protected function processAcl(): array
    {
        global $db;
        $mapByElement = [];

        $qb = $db->getConnection()->createQueryBuilder();
        $qb->select('*')
            ->from('acl_roles')
            ->where('deleted = 0');
        $stmt = $qb->executeQuery();

        while ($row = $stmt->fetchAssociative()) {
            $roleId = $row['id'];
            $elementMap = [
                'files' => [],
                'db' => [
                    'acl_roles' => [
                        $row,
                    ],
                ],
            ];
            $elementData = ['Name' => $row['name'], 'Description' => $row['description']];

            $tablesLinkedByRoleId = [
                'acl_fields',
                'acl_roles_actions',
                'acl_roles_users',
            ];

            foreach ($tablesLinkedByRoleId as $tableName) {
                $elementMap['db'][$tableName] = [];
                $qb = $db->getConnection()->createQueryBuilder();
                $expr = $qb->expr();
                $qb->select('*')
                    ->from($tableName)
                    ->where($expr->eq('role_id', $expr->literal($roleId)))
                    ->andWhere('deleted = 0');
                $stmtEl = $qb->executeQuery();

                while ($rowEl = $stmtEl->fetchAssociative()) {
                    $elementMap['db'][$tableName][] = $rowEl;
                }
            }

            $elementMap['db']['acl_actions'] = [];
            $subQuery = $db->getConnection()->createQueryBuilder();
            $expr = $subQuery->expr();
            $subQuery->select('action_id')
                ->from('acl_roles_actions')
                ->where($expr->eq('role_id', $expr->literal($roleId)));
            $qb = $db->getConnection()->createQueryBuilder();
            $qb->select('*')
                ->from('acl_actions')
                ->where('id IN (' . $subQuery->getSQL() . ')')
                ->andWhere('deleted = 0');
            $stmtEl = $qb->executeQuery();

            while ($rowEl = $stmtEl->fetchAssociative()) {
                $elementMap['db']['acl_actions'][] = $rowEl;
            }

            $elementMap['db']['acl_role_sets_acl_roles'] = [];
            $qb = $db->getConnection()->createQueryBuilder();
            $expr = $qb->expr();
            $qb->select('*')
                ->from('acl_role_sets_acl_roles')
                ->where($expr->eq('acl_role_id', $expr->literal($roleId)))
                ->andWhere('deleted = 0');
            $stmtEl = $qb->executeQuery();

            while ($rowEl = $stmtEl->fetchAssociative()) {
                $elementMap['db']['acl_role_sets_acl_roles'][] = $rowEl;
            }

            $elementMap['db']['acl_role_sets'] = [];
            $subQuery = $db->getConnection()->createQueryBuilder();
            $expr = $subQuery->expr();
            $subQuery->select('acl_role_set_id')
                ->from('acl_role_sets_acl_roles')
                ->where($expr->eq('acl_role_id', $expr->literal($roleId)));
            $qb = $db->getConnection()->createQueryBuilder();
            $qb->select('*')
                ->from('acl_role_sets')
                ->where('id IN (' . $subQuery->getSQL() . ')')
                ->andWhere('deleted = 0');
            $stmtEl = $qb->executeQuery();

            while ($rowEl = $stmtEl->fetchAssociative()) {
                $elementMap['db']['acl_role_sets'][] = $rowEl;
            }

            $mapByElement[] = ['data' => $elementData, 'map' => $elementMap];
        }

        return $mapByElement;
    }

    /**
     * Extract customizations for category 'language'
     * @return array
     */
    protected function processLanguage(): array
    {
        $mapByElement = [];
        $langFileRegex = '/.*\.lang\.php/';

        //process for lang files for all modules
        $dirFiles = \SugarAutoLoader::scanDir('custom/Extension/modules');
        foreach ($dirFiles as $folderName => $folderContent) {
            $moduleName  = $folderName;
            $langFoldersT = [];

            if ($folderContent != 1) {
                if (isset($folderContent['Ext']['Language'])) {
                    foreach ($folderContent['Ext']['Language'] as $key => $value) {
                        if (preg_match($langFileRegex, $key)) {
                            $langFolderGroup = [
                                'custom/modules' => "custom/modules/<moduleName>/language/{$key}",
                                'custom/Extension/modules' => "custom/Extension/modules/<moduleName>/Ext/Language/{$key}",
                            ];

                            array_push($langFoldersT, $langFolderGroup);
                        }
                    }
                }
                $elementMap = ['files' => [], 'db' => []];
                $elementData = ['Module' => $moduleName];
                $addToMap = false;
                foreach ($langFoldersT as $key => $langFolders) {
                    foreach ($langFolders as $folder => $path) {
                        $langFilePath = str_replace('<moduleName>', $moduleName, $path);
                        if (\SugarAutoLoader::fileExists($langFilePath)) {
                            $elementMap['files'][] = $this->getFileData($langFilePath);
                            $addToMap = true;
                        }
                    }
                }
                if ($addToMap) {
                    $mapByElement[] = ['data' => $elementData, 'map' => $elementMap];
                }
            }
        }

        return $mapByElement;
    }

    /**
     * Extract customizations for category 'installed_packages'
     * @return array
     */
    protected function processInstalledPackages(): array
    {
        global $db;
        $installedPackages = [];

        $qb = $db->getConnection()->createQueryBuilder();
        $qb->select(['uh.id', 'uh.status', 'uh.filename', 'uh.version', 'uh.name', 'uh.description'])
            ->from('upgrade_history', 'uh')
            ->where($qb->expr()->eq('uh.status', $qb->expr()->literal('installed')))
            ->andWhere($qb->expr()->eq('uh.type', $qb->expr()->literal('module')))
            ->andWhere('deleted = 0');
        $stmt = $qb->executeQuery();

        $installedPackages = $stmt->fetchAllAssociative();

        $stagedPackages = [];

        $subQuery = $db->getConnection()->createQueryBuilder();
        $subQuery->select('uh.name')
            ->from('upgrade_history', 'uh')
            ->where($subQuery->expr()->eq('uh.status', $subQuery->expr()->literal('installed')))
            ->andWhere($subQuery->expr()->eq('uh.type', $subQuery->expr()->literal('module')))
            ->andWhere('deleted = 0');
        $qb = $db->getConnection()->createQueryBuilder();
        $qb->select(['uh.id', 'uh.status', 'uh.filename', 'uh.version', 'uh.name', 'uh.description'])
            ->from('upgrade_history', 'uh')
            ->where($qb->expr()->eq('uh.status', $qb->expr()->literal('staged')))
            ->andWhere('uh.name NOT IN (' . $subQuery->getSQL() . ')')
            ->andWhere($qb->expr()->eq('uh.type', $qb->expr()->literal('module')))
            ->andWhere('deleted = 0');
        $stmt = $qb->executeQuery();

        $stagedPackages = $stmt->fetchAllAssociative();

        // Add the staged packages that have no other version installed
        foreach ($stagedPackages as $stagedPackage) {
            array_push($installedPackages, $stagedPackage);
        }

        return $installedPackages;
    }

    /**
     * Get data for dashboards
     * @param array $dashboards
     */
    public function getDashboardsData(array &$dashboards)
    {
        global $db;
        foreach ($dashboards as $key => $element) {
            if (empty($element['map']['id'])) {
                throw new \SugarApiExceptionInvalidParameter('dashboards element should be {map:{id:"id"}}');
            }
            $dashboardId = $element['map']['id'];
            $qb = $db->getConnection()->createQueryBuilder();
            $qb->select('*')
                ->from('dashboards')
                ->where($qb->expr()->eq('id', $qb->expr()->literal($dashboardId)))
                ->andWhere('deleted = 0');
            $stmt = $qb->executeQuery();

            while ($dashboardRow = $stmt->fetchAssociative()) {
                $dashboards[$key]['map']['db']['dashboards'][] = $dashboardRow;
            }
            $stmt->free();
        }
    }

    /**
     * Get data for advanced workflows
     * @param array $list
     */
    public function getAdvancedWorkflowsData(array &$list)
    {
        global $db;

        $prjIdLinkedTables = [
            'pmse_bpm_dynamic_forms',
            'pmse_bpm_process_definition',
            'pmse_bpmn_diagram',
            'pmse_bpmn_process',
            'pmse_bpmn_event',
            'pmse_bpm_event_definition',
            'pmse_bpmn_bound',
            'pmse_bpm_related_dependency',
            'pmse_bpmn_flow',
            'pmse_bpmn_gateway',
            'pmse_bpm_gateway_definition',
            'pmse_bpmn_artifact',
        ];
        $proIdLinkedTables = [
            'pmse_bpmn_activity',
            'pmse_bpm_activity_definition',
            'pmse_bpm_event_definition',
        ];

        foreach ($list as $element) {
            if (empty($element['map']['db']['pmse_project']['0']['id'])) {
                throw new \SugarApiExceptionInvalidParameter('advanced_workflows element should be {map:{db:{pmse_project:[{id:"id"}]}}}');
            }
        }

        $projectIds = array_map(fn($row) => $row['map']['db']['pmse_project']['0']['id'], $list);
        $mapProjectIdToRowKey = array_flip($projectIds);
        $mapProcessIdToRowKey = [];

        foreach ($prjIdLinkedTables as $key => $tableName) {
            foreach ($list as $rowKey => $row) {
                $list[$rowKey]['map']['db'][$tableName] = [];
            }

            $qb = $db->getConnection()->createQueryBuilder();
            if (!isset($projectIdsEscaped)) {
                $projectIdsEscaped = array_map(fn($id) => $qb->expr()->literal($id), $projectIds);
            }
            $qb->select('*')
                ->from($tableName)
                ->where($qb->expr()->in('prj_id', $projectIdsEscaped));
            $stmt = $qb->executeQuery();
            while ($rowC = $stmt->fetchAssociative()) {
                $rowKey = $mapProjectIdToRowKey[$rowC['prj_id']];
                $list[$rowKey]['map']['db'][$tableName][] = $rowC;
                if ($tableName === 'pmse_bpmn_process') {
                    $mapProcessIdToRowKey[$rowC['id']] = $rowKey;
                }
            }
        }

        if (!$mapProcessIdToRowKey) {
            return;
        }

        foreach ($proIdLinkedTables as $key => $tableName) {
            foreach ($list as $rowKey => $row) {
                $list[$rowKey]['map']['db'][$tableName] = [];
            }

            $qb = $db->getConnection()->createQueryBuilder();
            if (!isset($processIdsEscaped)) {
                $processIdsEscaped = array_map(fn($id) => $qb->expr()->literal($id), array_keys($mapProcessIdToRowKey));
            }
            $qb->select('*')
                ->from($tableName)
                ->where($qb->expr()->in('pro_id', $processIdsEscaped));
            $stmt = $qb->executeQuery();
            while ($rowC = $stmt->fetchAssociative()) {
                $rowKey = $mapProcessIdToRowKey[$rowC['pro_id']];
                $list[$rowKey]['map']['db'][$tableName][] = $rowC;
            }
        }

        foreach ($list as $rowKey => $row) {
            // pmse_bpm_event_definition are selected 2 times based on different criteria
            $eventsDefList = $list[$rowKey]['map']['db']['pmse_bpm_event_definition'];
            if (!empty($eventsDefList)) {
                // Sort the list, to make sure there are no 2 the same events
                $list[$rowKey]['map']['db']['pmse_bpm_event_definition'] = array_unique(
                    $eventsDefList,
                    SORT_REGULAR,
                );
            }
        }
    }

    /**
     * Return the file path and content
     * @param string $filePath
     * @return array
     */
    protected function getFileData(string $filePath): array
    {
        return [
            'path' => $filePath,
            'content' => base64_encode(sugar_file_get_contents($filePath)),
        ];
    }

    /**
     * Add file data to the map
     * @param string $filePath
     * @param array $map
     */
    protected function addFileData(string $filePath, array &$map): void
    {
        if (\SugarAutoLoader::fileExists($filePath)) {
            $map['files'][] = $this->getFileData($filePath);
        }
    }

    /**
     * Generate the content for a new package
     * @param array $customizations
     * @return array|bool
     */
    public function create($customizations, $packageName)
    {
        $files = $this->retrieveAllFiles($customizations);
        // If files size is under limit -> create package
        if ($this->calculateFilesSize($files)) {
            $this->createPackageRelatedFiles($customizations, $packageName);
            return $customizations;
        }
        throw new \SugarApiExceptionRequestMethodFailure('the resulting file is too big, reorganize customizations');
    }

    /**
     * Get acceptable versions for the package
     * @param string $majorVersion
     * @return array
     */
    protected function getAcceptableVersions(string $majorVersion): array
    {
        $acceptableVersions = [];
        for ($i = 0; $i < 3; $i++) {
            $acceptableVersion = $majorVersion - $i;
            if ($acceptableVersion < 25) {
                array_unshift($acceptableVersions, "'14.*'");
                break;
            }
            array_unshift($acceptableVersions, "'$acceptableVersion.*'");
        }
        return $acceptableVersions;
    }

    /**
     * Retrieve all files from customizations
     * @param array $customizations
     * @param string $packageName
     */
    protected function createPackageRelatedFiles(&$customizations, $packageName)
    {
        $dbCustomizations = [];
        $relationshipsData = [];
        $manifestCopy = [];
        $langFileRegex = '/.*\.lang\.php/';

        foreach ($customizations as $key => $value) {
            $map = $value['map'];
            if ($map['files']) {
                foreach ($map['files'] as $i => $fileInfo) {
                    $filePath = $fileInfo['path'];
                    if ($key === 'language' && preg_match($langFileRegex, $filePath)) {
                        $ciLanguageFileName = '.CI-' . \TimeDate::getInstance()->nowDbDate() . '.php';
                        $toFilePath = str_replace(".lang.php", $ciLanguageFileName, $filePath);
                        $manifestCopy[] = ['from' => '<basepath>/' . $filePath, 'to' => $toFilePath];
                    } else {
                        $manifestCopy[] = ['from' => '<basepath>/' . $filePath, 'to' => $filePath];
                    }
                    if ((strpos($filePath, 'custom/metadata') === 0) && ($key == 'relationships')) {
                        $relationshipsData[] = $filePath;
                    }
                }
            }
            if ($map['db']) {
                foreach ($map['db'] as $tableName => $dbContent) {
                    if (!isset($dbCustomizations[$tableName])) {
                        $dbCustomizations[$tableName] = [];
                    }
                    $dbCustomizations[$tableName] = array_merge($dbCustomizations[$tableName], $dbContent);
                }
            }
        }

        $contentRelatipshipsData = "<?php\n\n" . '$relationships_data = ' . var_export($relationshipsData, true) . ';';
        $contentDbCustomizations = "<?php\n\n" . '$db_customizations = ' . var_export($dbCustomizations, true) . ';';

        $timeStamp = date('Y-m-d H:i:s');
        $timeStampName = date('Y_m_d_His');
        $majorVersion = getMajorVersion($GLOBALS['sugar_version']);
        $acceptableVersions = $this->getAcceptableVersions($majorVersion);
        $contentManifest = '<?php
/*********************************************************************************
 * The contents of this file are subject to the SugarCRM Master Subscription
 * Agreement (License) which can be viewed at
 * http://www.sugarcrm.com/crm/master-subscription-agreement
 * By installing or using this file, You have unconditionally agreed to the
 * terms and conditions of the License, and You may not use this file except in
 * compliance with the License.  Under the terms of the license, You shall not,
 * among other things: 1) sublicense, resell, rent, lease, redistribute, assign
 * or otherwise transfer Your rights to the Software, and 2) use the Software
 * for timesharing or service bureau purposes such as hosting the Software for
 * commercial gain and/or for the benefit of a third party.  Use of the Software
 * may be subject to applicable fees and any use of the Software without first
 * paying applicable fees is strictly prohibited.  You do not have the right to
 * remove SugarCRM copyrights from the source code or user interface.
 *
 * All copies of the Covered Code must include on each user interface screen:
 *  (i) the Powered by SugarCRM logo and
 *  (ii) the SugarCRM copyright notice
 * in the same form as they appear in the distribution.  See full license for
 * requirements.
 *
 * Your Warranty, Limitations of liability and Indemnity are expressly stated
 * in the License.  Please refer to the License for the specific language
 * governing these rights and limitations under the License.  Portions created
 * by SugarCRM are Copyright (C) 2004-2012 SugarCRM, Inc.; All Rights Reserved.
 ********************************************************************************/

// THIS CONTENT IS GENERATED BY PACKAGE BUILDER
$manifest = [
    \'acceptable_sugar_versions\' => [' . implode(',', $acceptableVersions) . '],
    \'acceptable_sugar_flavors\' => [
    ],
    \'readme\' => \'\',
    \'key\' => \'\',
    \'author\' => \'SugarCRM\',
    \'description\' => \'A bundle of configurations and customizations.\',
    \'icon\' => \'\',
    \'is_uninstallable\' => true,
    \'name\' => \'' . $packageName . '\',
    \'published_date\' => \'' . $timeStamp . '\',
    \'type\' => \'module\',
    \'version\' => \'1.0.0\',
    \'remove_tables\' => \'false\',
];

$installdefs = [
    \'id\' => \'' . $packageName . '\',
    \'copy\' =>  ' . var_export($manifestCopy, true) . ',
    \'post_execute\' => [\'<basepath>/post_install_script.php\'],
];';

        $customizations['package_info'] = ['packageName' => $packageName];
        $customizations['package_files'] = ['map' => ['files' => []]];
        $customizations['package_files']['map']['files'][] = [
            'path' => 'manifest.php',
            'content' => base64_encode($contentManifest),
        ];
        $customizations['package_files']['map']['files'][] = [
            'path' => 'db_customizations.php',
            'content' => base64_encode($contentDbCustomizations),
        ];
        $customizations['package_files']['map']['files'][] = [
            'path' => 'relationships_data.php',
            'content' => base64_encode($contentRelatipshipsData),
        ];
        $customizations['package_files']['map']['files'][] = [
            'path' => 'post_install_script.php',
            'content' => base64_encode(sugar_file_get_contents('src/PackageBuilder/post_install_script.php')),
        ];
        $customizations['package_files']['map']['files'][] = [
            'path' => 'post_install_functions.php',
            'content' => base64_encode(sugar_file_get_contents('src/PackageBuilder/post_install_functions.php')),
        ];
    }

    /**
     * Retrieve all files from customizations
     *
     * @param array $customizations
     * @return array
     */
    protected function retrieveAllFiles(array $customizations): array
    {
        $retrievedFiles = [];

        foreach ($customizations as $key => $customization) {
            if (isset($customization['map'])) {
                $customizationMap = $customization['map'];
                if (isset($customizationMap['files'])) {
                    $files = $customizationMap['files'];
                    foreach ($files as $fKey => $file) {
                        array_push($retrievedFiles, $file['path']);
                    }
                }
            }
        }

        return $retrievedFiles;
    }

    /**
     * Calculate the size of the files in MB
     *
     * @param array $files
     * @return bool
     */
    protected function calculateFilesSize(array $files): bool
    {
        $totalSize = 0;
        $allFilesUnder25MB = true;

        foreach ($files as $filePath) {
            if (strpos($filePath, self::CUSTOM_REL_LABEL) !== false ||
                strpos($filePath, self::CUSTOM_FIELD_LABEL) !== false) {
                continue;
            }

            $size = $this->fileGetSize($filePath, false);

            $totalSize += $size;
            $totalSizeInMB = $totalSize / (1024 * 1024);

            if ($totalSizeInMB > 25) {
                $allFilesUnder25MB = false;
            }
        }

        return $allFilesUnder25MB;
    }
}
