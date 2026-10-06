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

// phpcs:disable PSR1.Classes.ClassDeclaration.MissingNamespace

use Sugarcrm\Sugarcrm\Dropdowns\DropdownsManager;

/**
 * API for Dropdown Editor.
 */
class DropdownEditorApi extends SugarApi
{
    /**
     * Language
     *
     * @var string
     */
    protected $dropdownLanguage;

    /**
     * Dropdown name
     *
     * @var string
     */
    protected $dropdownName;

    /**
     * Comparison Language
     *
     * @var string
     */
    protected $comparisonLanguage;

    /**
     * Role
     *
     * @var string
     */
    protected $dropdownRole;

    /**
     * formatting
     *
     * @var bool
     */
    protected $formatting;

    /**
     * @return array
     */
    public function registerApiRest(): array
    {
        return [
            'getDropdownDetails' => [
                'reqType' => 'GET',
                'path' => ['Administration', 'dropdownEditor', '?'],
                'pathVars' => ['', '', 'dropdownName'],
                'method' => 'getDropdownDetails',
                'shortHelp' => 'Get details of dropdown that can be changed',
                'longHelp' => 'modules/Administration/clients/base/api/help/DropdownEditor/getDropdownDetails.html',
                'exceptions' => ['SugarApiExceptionNotAuthorized'],
                'ignoreSystemStatusError' => true,
                'minVersion' => '11.27',
            ],
            'create' => [
                'reqType' => 'POST',
                'path' => ['Administration', 'dropdownEditor', 'create'],
                'pathVars' => ['', '', ''],
                'method' => 'create',
                'shortHelp' => 'Create Dropdown DOM issue as a file in customer storage directory',
                'longHelp' => 'modules/Administration/clients/base/api/help/DropdownEditor/create.html',
                'exceptions' => ['SugarApiExceptionNotAuthorized'],
                'ignoreSystemStatusError' => true,
                'minVersion' => '11.27',
            ],
            'update' => [
                'reqType' => 'PUT',
                'path' => ['Administration', 'dropdownEditor', '?'],
                'pathVars' => ['', '', 'dropdownName'],
                'method' => 'updateDropdown',
                'shortHelp' => 'This method updates dropdown details',
                'longHelp' => 'modules/Administration/clients/base/api/help/DropdownEditor/update.html',
                'ignoreSystemStatusError' => true,
                'minVersion' => '11.27',
            ],
            'restore' => [
                'reqType' => 'PUT',
                'path' => ['Administration', 'dropdownEditor', 'restore', '?'],
                'pathVars' => ['', '', '', 'dropdownName'],
                'method' => 'restoreDropdown',
                'shortHelp' => 'This method restores the dropdown list to its default data',
                'longHelp' => 'modules/Administration/clients/base/api/help/DropdownEditor/restore.html',
                'exceptions' => ['SugarApiExceptionNotAuthorized'],
                'ignoreSystemStatusError' => true,
                'minVersion' => '11.27',
            ],
        ];
    }

    /**
     * Gets details of dropdown
     *
     * @param ServiceBase $api The RestService object
     * @param array $args Arguments passed to the service
     *
     * @return array
     * @throws SugarApiExceptionNotAuthorized
     */
    public function getDropdownDetails(ServiceBase $api, array $args): array
    {
        global $app_list_strings, $locale;
        $this->ensureAdminOrDeveloperAccessToAnyModule($api);

        $this->dropdownName = $args['dropdownName'];
        if (!array_key_exists($this->dropdownName, $app_list_strings)) {
            throw new SugarApiExceptionNotFound("Dropdown `{$this->dropdownName}` not found");
        }

        $this->dropdownLanguage = $args['language'] ?? $locale->getAuthenticatedUserLanguage();
        $this->comparisonLanguage = $args['comparisonLanguage'] ?? null;
        $this->dropdownRole = $args['role'] ?? null;
        $comparisonLanguageList = $this->getComparisonLanguageList();
        $roleOptions = $this->getRoleOptions();
        $dropdownOptionsData = $this->getDropdownOptionsData();
        $classifications = $this->getDropdownClassifications();
        $classificationsData = $this->preprocessingDropdownClassificationsData($classifications);
        $styleData = DropdownsManager::getDropdownStyle($this->dropdownName);
        $noStyleLbl = translate('LBL_DROPDOWN_NO_STYLE', 'Administration');

        $output = [];

        foreach ($dropdownOptionsData as $key => $option) {
            $output[$key] = [
                'label' => $option,
                'value' => $key,
                'role' => $roleOptions[$key] ?? null,
                'style' => [
                    'backgroundColor' => $styleData[$key]['backgroundColor'] ?? '',
                    'prevBgColor' => $styleData[$key]['prevBgColor'] ?? '',
                    'icon' => [
                        'class' => $styleData[$key]['icon']['class'] ?? '',
                        'color' => $styleData[$key]['icon']['color'] ?? '',
                    ],
                    'text' => [
                        'color' => $styleData[$key]['text']['color'] ?? '',
                        'isBold' => $styleData[$key]['text']['isBold'] ?? false,
                        'isItalic' => $styleData[$key]['text']['isItalic'] ?? false,
                        'isLineThrough' => $styleData[$key]['text']['isLineThrough'] ?? false,
                        'isUnderline' => $styleData[$key]['text']['isUnderline'] ?? false,
                    ],
                    'colorway' => [
                        'title' => $styleData[$key]['colorway']['title'] ?? $noStyleLbl,
                        'class' => $styleData[$key]['colorway']['class'] ?? 'no_style',
                    ],
                ],
            ];

            if (!empty($classificationsData)) {
                $output[$key]['classification'] = $classificationsData[$key];
            }

            if ($this->comparisonLanguage && $this->comparisonLanguage !== $this->dropdownLanguage) {
                $output[$key]['comparisonLabel'] = $comparisonLanguageList[$key] ?? null;
            }
        }

        return [
            'dropdownData' => $output,
            'dropdownOrder' => array_keys($dropdownOptionsData ?? []),
            'classifications' => (object)$classifications,
            'ootb' => $this->existOOTB(),
            'formatting' => $styleData['applyFormatting'] ?? false,
        ];
    }

    /**
     * Create Dropdown DOM issue as a php-file in customer storage directory
     *
     * @param ServiceBase $api The RestService object
     * @param array $args Arguments passed to the service
     * @return array
     * @throws SugarApiExceptionNotAuthorized
     */
    public function create(ServiceBase $api, array $args): array
    {
        global $app_list_strings;
        global $locale;

        $this->ensureAdminOrDeveloperAccessToAnyModule($api);
        $this->requireArgs($args, ['language', 'role', 'formatting']);

        $this->dropdownName = trim($args['dropdown_name']) ?? null;
        $this->dropdownName = filter_var($this->dropdownName, FILTER_SANITIZE_ADD_SLASHES);

        if (!$this->dropdownName) {
            throw new SugarApiExceptionNotFound('Required parameter missing: `dropdown_name` ');
        }

        if (!$this->isValidDropdownName($this->dropdownName)) {
            throw new SugarApiExceptionInvalidParameter(
                'Invalid dropdown name: ' . $this->dropdownName
            );
        }

        if (array_key_exists($this->dropdownName, $app_list_strings)) {
            throw new SugarApiExceptionEditConflict();
        }

        $this->dropdownLanguage = $args['language'] ?? $locale->getAuthenticatedUserLanguage();
        $this->dropdownRole = $args['role'];
        $this->formatting = filter_var($args['formatting'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        $this->formatting = $this->formatting ?? false;

        $dropdownData = $this->parseDropdownData($args);

        if (!$this->isValidDropdownData($dropdownData)) {
            throw new SugarApiExceptionInvalidParameter('Invalid dropdown data provided.');
        }

        if ($dropdownData) {
            $this->saveDropdown($dropdownData);
            $this->saveDropdownStyle($dropdownData);
        }

        return ['success' => 'true'];
    }

    /**
     * Validate dropdown data
     *
     * @param mixed $dropdownData
     * @return bool
     */
    protected function isValidDropdownData(mixed $dropdownData): bool
    {
        $savedDDItems = $this->getDropdownOptionsData();

        if (is_null($dropdownData)) {
            return true;
        }

        if (!is_array($dropdownData)) {
            return false;
        }

        $isValid = true;
        foreach ($dropdownData as $dd) {
            $ddValue = $dd['value'] ?? null;

            if ($ddValue === null) {
                $isValid = false;
                break;
            }

            // Skip validation for existing dropdown items
            if (is_array($savedDDItems) && isset($savedDDItems[$ddValue])) {
                continue;
            }

            if ($ddValue !== '' && !$this->isValidDropdownItemName($ddValue)) {
                $isValid = false;
                break;
            }
        }

        return $isValid;
    }

    /**
     * Uptates details of dropdown
     *
     * @param ServiceBase $api The RestService object
     * @param array $args Arguments passed to the service
     *
     * @return array
     * @throws SugarApiExceptionNotAuthorized
     */
    public function updateDropdown(ServiceBase $api, array $args): array
    {
        global $app_list_strings;

        $this->ensureAdminOrDeveloperAccessToAnyModule($api);

        $this->dropdownName = $args['dropdownName'];
        if (!array_key_exists($this->dropdownName, $app_list_strings)) {
            throw new SugarApiExceptionNotFound("Dropdown `{$this->dropdownName}` not found");
        }

        $this->requireArgs($args, ['language', 'comparisonLanguage', 'role', 'formatting']);
        $this->dropdownLanguage = $args['language'];
        $this->comparisonLanguage = $args['comparisonLanguage'];
        $this->dropdownRole = $args['role'];
        $this->formatting = filter_var($args['formatting'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        $this->formatting = $this->formatting ?? false;

        $dropdownData = $this->parseDropdownData($args);

        if (!$this->isValidDropdownData($dropdownData)) {
            throw new SugarApiExceptionInvalidParameter('Invalid dropdown data provided.');
        }

        if ($dropdownData) {
            $this->saveDropdown($dropdownData);
            $this->saveDropdownStyle($dropdownData);
            $this->saveDropdownClassifications($dropdownData);
            $this->saveRoleDropDownFilter($dropdownData);
        }

        return ['success' => 'true'];
    }

    /**
     * Restore the dropdown list to its default data
     *
     * @param ServiceBase $api The RestService object
     * @param array $args Arguments passed to the service
     *
     * @return array
     * @throws SugarApiExceptionNotAuthorized
     */
    public function restoreDropdown(ServiceBase $api, array $args): array
    {
        global $app_list_strings;

        $this->ensureAdminOrDeveloperAccessToAnyModule($api);

        $this->dropdownName = $args['dropdownName'];
        if (!array_key_exists($this->dropdownName, $app_list_strings)) {
            throw new SugarApiExceptionNotFound("Dropdown `{$this->dropdownName}` not found");
        }

        $this->removeCustomDropdown();
        $this->removeCustomClassifications();
        $this->removeCustomRoles();
        $this->removeCustomStyles();

        return ['success' => 'true'];
    }

    /**
     * Remove the custom dropdown list for all languages
     *
     * @return void
     */
    protected function removeCustomDropdown(): void
    {
        $allLanguages = get_languages();
        foreach ($allLanguages as $lang => $langName) {
            $fileName = $this->getExtensionFilePath($this->dropdownName, $lang);
            if ($fileName && check_file_name($fileName) && file_exists($fileName)) {
                unlink($fileName);
            }
        }

        $this->finalize($allLanguages);
    }

    /**
     * Remove the custom classifications for the dropdown list
     *
     * @return void
     */
    protected function removeCustomClassifications(): void
    {
        $classifier = DropdownClassifier::getInstance();
        $fileName = $classifier->getExtensionFilePath($this->dropdownName);
        if ($fileName && check_file_name($fileName) && file_exists($fileName)) {
            unlink($fileName);

            $moduleInstaller = new ModuleInstaller();
            $moduleInstaller->silent = true;
            $moduleInstaller->rebuild_extensions([], ['dropdown_classifications']);
        }
    }

    /**
     * Remove the custom roles for the dropdown list
     *
     * @return void
     */
    protected function removeCustomRoles(): void
    {
        $roles = ACLRole::getAllRoles();
        foreach ($roles as $role) {
            $this->dropdownRole = $role->id;
            $fileName = $this->getFilePath();
            if ($fileName && check_file_name($fileName) && file_exists($fileName)) {
                unlink($fileName);
            }
        }

        $moduleInstaller = new ModuleInstaller();
        $moduleInstaller->silent = true;
        $moduleInstaller->rebuild_dropdown_filters();
    }

    /**
     * Remove the custom styles for the dropdown list
     *
     * @return void
     */
    protected function removeCustomStyles(): void
    {
        DropdownsManager::deleteDropdownStyle($this->dropdownName . '_style');

        // Rebuild the extensions to remove the custom styles
        $moduleInstaller = new ModuleInstaller();
        $moduleInstaller->silent = true;
        $moduleInstaller->rebuild_extensions([], ['dropdown_styles']);
        DropdownsManager::rebuildDropdownsStyle();
    }

    /**
     * Parses DropdownData
     *
     * @param array $params The API Query Params
     *
     * @return array|null The dropdown data
     */
    protected function parseDropdownData($params): ?array
    {
        $dropdownData = $params['dropdownData'];

        if (is_string($params['dropdownData'])) {
            $dropdownData = json_decode($params['dropdownData'], true);
        }

        if (!is_array($dropdownData)) {
            $jsonErrorMessage = 'Decoding dropdown classifications for ' . $this->dropdownName . ' failed';
            LoggerManager::getLogger()->error($jsonErrorMessage);
            throw new SugarApiExceptionError($jsonErrorMessage);
        }

        // If client provided an explicit order, reorder the decoded associative array accordingly
        if (!empty($params['dropdownOrder']) && is_array($params['dropdownOrder'])) {
            $ordered = [];
            // Normalize order keys to strings to avoid integer casting mismatches
            $orderKeys = array_map('strval', $params['dropdownOrder']);
            foreach ($orderKeys as $k) {
                if (array_key_exists($k, $dropdownData)) {
                    $ordered[$k] = $dropdownData[$k];
                }
            }
            // Append any keys missing from dropdownOrder to preserve data integrity
            foreach ($dropdownData as $k => $v) {
                if (!array_key_exists($k, $ordered)) {
                    $ordered[$k] = $v;
                }
            }
            $dropdownData = $ordered;
        }

        return $dropdownData ?? null;
    }

    /**
     * Saves any changes made to the dropdown
     *
     * @param array|null $dropdownData The dropdown data
     */
    protected function saveDropdown(array|null $dropdownData)
    {
        if (!$dropdownData) {
            return;
        }

        $dropdown = $this->setDropdown($dropdownData);
        // Now synch up the keys in other languages to ensure that removed/added
        // Drop down values work properly under all langs.
        // If skip_sync, we don't want to sync ALL languages
        $this->synchDropDown($dropdown);
        $this->saveDropdownToLang($dropdown, $this->dropdownLanguage);
        $this->finalize($this->dropdownLanguage);
    }

    /**
     * Updates dropdown
     *
     * @return array The dropdown metadata
     */
    protected function setDropdown($dropdownData): array
    {

        $dropdown = array_map(function ($value) {
            return $value['label'];
        }, $dropdownData);

        return $dropdown;
    }

    /**
     *  Ensures that the set of dropdown keys is consistant accross all languages.
     *
     * @param array $dropdown The dropdown currently being saved
     */
    protected function synchDropDown($dropdown)
    {
        $allLanguages = get_languages();
        foreach ($allLanguages as $lang => $langName) {
            if ($lang != $this->dropdownLanguage) {
                $listStrings = return_app_list_strings_language($lang, false);
                if (isset($listStrings[$this->dropdownName]) && is_array($listStrings[$this->dropdownName])) {
                    $langDropDown = $this->synchDDKeys($dropdown, $listStrings[$this->dropdownName]);
                } else {
                    //if the dropdown does not exist in the language, just use what we have.
                    $langDropDown = $dropdown;
                }
                $this->saveDropdownToLang($langDropDown, $lang);
            }
        }
    }

    /**
     * Saves Dropdown contents to the language
     *
     * @param array $dropdown The dropdown currently being saved
     * @param string $lang saved to the language
     */
    protected function saveDropdownToLang($dropdown, $lang)
    {
        $contents = $this->getExtensionContents($dropdown);
        $this->saveContents($contents, $lang);
    }

    /**
     * Retrieves the contents for a language extension that includes only the dropdown modified in the contents
     * @param array $dropdown
     *
     * @return string
     */
    protected function getExtensionContents($dropdown): string
    {
        $edropdownName = var_export($this->dropdownName, true);
        $contents = "<?php\n // created: " . date('Y-m-d H:i:s') . "\n";
        $contents .= "\n\$app_list_strings[$edropdownName]=" . var_export($dropdown, true) . ';';

        return $contents;
    }

    /**
     * Saves the dropdown as an Extension, and rebuilds the extensions for given language
     *
     * @param string $contents - the edited dropdown contents
     * @param string $lang - the edited dropdown language
     *
     * @return bool Success
     */
    protected function saveContents($contents, $lang): bool
    {
        $fileName = $this->getExtensionFilePath($this->dropdownName, $lang);
        if ($fileName) {
            if (!check_file_name($fileName)) {
                return false;
            }

            if (sugar_file_put_contents_atomic($fileName, $contents) !== false) {
                return true;
            }
            $GLOBALS['log']->fatal("Unable to write edited dropdown language to file: $fileName");
        }
        return false;
    }

    /**
     * Gets custom language file path for the dropdown
     *
     * @param string $dropdownName
     * @param string $lang
     *
     * @return string file name
     */
    protected function getExtensionFilePath($dropdownName, $lang): ?string
    {
        $dirName = 'custom/Extension/application/Ext/Language';
        if (SugarAutoLoader::ensureDir($dirName)) {
            $fileName = "$dirName/$lang.sugar_$dropdownName.php";

            return $fileName;
        } else {
            $GLOBALS['log']->fatal("Unable to create dir: $dirName");
        }

        return null;
    }

    /**
     * Synchronize dropdown with all languages
     *
     * @param array $dom
     * @param array $sub
     *
     * @return array
     */
    private function synchDDKeys($dom, $sub): array
    {
        //check for extra keys
        foreach ($sub as $key => $value) {
            if (!isset($dom[$key])) {
                unset($sub[$key]);
            }
        }
        //check for missing keys
        foreach ($dom as $key => $value) {
            if (!isset($sub[$key])) {
                $sub[$key] = $value;
            }
        }
        return $sub;
    }

    /**
     * Clears the js cache and rebuilds the language files
     *
     * @param string $lang - language to be rebuilt, and cache cleared
     */
    public function finalize($lang)
    {
        if (!is_array($lang)) {
            $lang = [$lang => $lang];
        }
        SugarAutoLoader::requireWithCustom('ModuleInstall/ModuleInstaller.php');
        $moduleInstallerClass = SugarAutoLoader::customClass('ModuleInstaller');
        $mi = new $moduleInstallerClass();
        $mi->silent = true;
        $mi->rebuild_languages($lang);

        sugar_cache_reset();
        sugar_cache_reset_full();
        clearAllJsAndJsLangFilesWithoutOutput();

        // Clear out the api metadata languages cache for selected language
        LanguageManager::invalidateJsLanguageCache();
        MetaDataManager::refreshLanguagesCache($lang);
    }

    /**
     * Saves any changes made to classifications of the dropdown
     *
     * @param array|null $dropdownData The API Query Params
     */
    protected function saveDropdownClassifications(array|null $dropdownData)
    {
        if (!$dropdownData) {
            return;
        }

        $classifications = $this->setDropdownClassifications($dropdownData);
        if (!empty($classifications)) {
            $classifier = DropdownClassifier::getInstance();
            $oldClassifications = $classifier->getClassificationsForDropdown($this->dropdownName);
            $newClassifications = array_replace_recursive($oldClassifications, $classifications);
            $classifier->saveClassifications($this->dropdownName, $newClassifications);

            $moduleInstaller = new ModuleInstaller();
            $moduleInstaller->silent = true;
            $moduleInstaller->rebuild_extensions([], ['dropdown_classifications']);
        }
    }

    /**
     * Updates classifications for the given dropdown
     *
     * @return array The dropdown classification metadata
     */
    protected function setDropdownClassifications($dropdownData): array
    {
        $classifications = DropdownClassifier::getInstance()->getClassificationsForDropdown($this->dropdownName);
        foreach ($classifications as $classificationKey => &$classification) {
            foreach ($dropdownData as $key => $value) {
                $classification['classifications'][$key] = $dropdownData[$key]['classification'][$classificationKey];
            }
        }

        return $classifications;
    }

    /**
     * Updates role name for the given dropdown
     */
    protected function saveRoleDropDownFilter($dropdownData)
    {
        if (in_array($this->dropdownRole, [0, '0', 'Base Layout', 'LBL_BASE_LAYOUT'])) {
            return;
        }

        $path = $this->getFilePath();
        $dir = dirname($path);
        if (!SugarAutoLoader::ensureDir($dir)) {
            $GLOBALS['log']->error("ParserRoleDropDownFilter :: Cannot create directory $dir");
            return;
        }
        $result = write_array_to_file(
            "role_dropdown_filters['{$this->dropdownName}']",
            $this->convertFormData($dropdownData),
            $path
        );
        if ($result) {
            $this->rebuildExtension();
            MetaDataManager::refreshSectionCache(MetaDataManager::MM_EDITDDFILTERS, [], [
                'role' => $this->dropdownRole,
            ]);
        }
    }

    /**
     * Returns a file path to the file that stores options for a given role and a dropdown name
     *
     * @return string
     */
    protected function getFilePath(): string
    {
        return 'custom/Extension/application/Ext/DropdownFilters/roles/' .
            $this->dropdownRole . '/' . $this->dropdownName . '.php';
    }

    /**
     * Converts form data to internal representation
     *
     * @return array Internal representation
     */
    protected function convertFormData($dropdownData): array
    {
        $converted = [];
        $blank = translate('LBL_BLANK', 'ModuleBuilder');
        foreach ($dropdownData as $key => $item) {
            if ($key === $blank) {
                $key = '';
            }

            $converted[$key] = (bool)($item['role'] ?? false);
        }

        return $converted;
    }

    /**
     * Rebuilds the extension for the dropdown role
     */
    protected function rebuildExtension()
    {
        SugarAutoLoader::requireWithCustom('ModuleInstall/ModuleInstaller.php');
        $moduleInstallerClass = SugarAutoLoader::customClass('ModuleInstaller');
        $moduleInstaller = new $moduleInstallerClass();
        $moduleInstaller->silent = true;
        $moduleInstaller->rebuild_role_dropdown_filters($this->dropdownRole);
    }

    /**
     * Parses and returns metadata of classifications defined for the given dropdown
     *
     * @return array The classification metadata for the dropdown
     */
    protected function getDropdownClassifications(): array
    {
        $classifications = DropdownClassifier::getInstance()->getClassificationsForDropdown($this->dropdownName);
        foreach ($classifications as &$classification) {
            // If 'options' is defined as a label key, translate it into the array of values
            if (is_string($classification['options'])) {
                $classification['options'] = translate($classification['options']);
            }

            // If no default is set, set the default as the first option
            if (!is_string($classification['default'])) {
                $classifications['default'] = $classification['options'][0] ?? '';
            }

            // Make sure the classification values are an existing key of the options
            foreach ($classification['classifications'] as &$classificationValue) {
                if (!isset($classification['options'][$classificationValue])) {
                    $classificationValue = $classification['default'];
                }
            }
        }

        return $classifications;
    }

    /**
     * Preprocess Dropdown Classifications Data to be more convenient for dropdown
     *
     * @return array
     */
    protected function preprocessingDropdownClassificationsData(array $classifications): array
    {
        $classificationsData = [];
        foreach ($classifications as $classificationKey => $classification) {
            // Make sure the classification values are an existing key of the options
            foreach ($classification['classifications'] as $key => $classificationValue) {
                $classificationsData[$key] = $classificationsData[$key] ?? [];
                $classificationsData[$key][$classificationKey] = $classificationValue;
            }
        }

        return $classificationsData;
    }

    /**
     * Get comparison language data
     *
     * @return array|null
     */
    protected function getComparisonLanguageList(): ?array
    {
        global $locale;

        $languageList = array_diff_key(get_languages(), [$this->dropdownLanguage => '']);
        if (!empty($languageList) && $this->dropdownLanguage !== $locale->getAuthenticatedUserLanguage()) {
            $this->comparisonLanguage = !empty($languageList[$this->comparisonLanguage]) ?
                $this->comparisonLanguage :
                array_key_first($languageList);
            $appData = return_app_list_strings_language($this->comparisonLanguage) ?? null;

            return $appData[$this->dropdownName] ?? null;
        }

        return null;
    }

    /**
     * Get options of dropdown included in a role.
     *
     * @param string $dropdownId
     *
     * @return array|null
     */
    protected function getRoleOptions(): ?array
    {
        $manager = MetaDataManager::getManager();
        $options = $manager->getEditableDropdownFilter($this->dropdownName, $this->dropdownRole);

        return $options ?? null;
    }

    /**
     * Get dropdown options data from the language file and filter them with the restrictedDropdowns filter
     *
     * @return array|null
     */
    protected function getDropdownOptionsData(): ?array
    {
        $appData = return_app_list_strings_language($this->dropdownLanguage) ?? null;
        $appData = array_diff_key($appData, DropDownBrowser::$restrictedDropdowns);

        return $appData[$this->dropdownName] ?? null;
    }

    /**
     * Checks if there is a dropdown in OOTB
     *
     * @return bool
     */
    protected function existOOTB(): bool
    {
        $file = 'include/language/' . $this->dropdownLanguage . '.lang.php';
        if (file_exists($file)) {
            include $file;
            return array_key_exists($this->dropdownName, $app_list_strings);
        }

        return false;
    }

    /**
     * Saves any changes made to style of the dropdown
     *
     * @param array|null $dropdownData The API Query Params
     */
    protected function saveDropdownStyle(array|null $dropdownData)
    {
        if (!$dropdownData) {
            return;
        }

        global $app_dropdowns_style;

        $dropdownStyle = $this->setDropdownStyle($dropdownData);
        if (!empty($dropdownStyle)) {
            $dropdownStyleName = $this->dropdownName . '_style';
            // adds dropdown options
            $dropdownStyle['applyFormatting'] = $this->formatting;
            $app_dropdowns_style[$dropdownStyleName] = $dropdownStyle;

            $contents = DropdownsManager::getExtensionContents($dropdownStyleName, $dropdownStyle);

            DropdownsManager::saveContents($this->dropdownName, $contents);
            DropdownsManager::clearDropdownsStyle();
        }
    }

    /**
     * Updates style for the given dropdown
     *
     * @return array The dropdown style metadata
     */
    protected function setDropdownStyle(array $dropdownData): array
    {
        $result = [];
        foreach ($dropdownData as $key => $item) {
            $style = $item['style'] ?? [];

            // Ensure nested structures exist
            $style['icon'] = $style['icon'] ?? [];
            $style['text'] = $style['text'] ?? [];
            $style['colorway'] = $style['colorway'] ?? [];

            // Sanitize color values: allow only HEX (#RGB or #RRGGBB) otherwise save as empty string
            $style['backgroundColor'] = $this->sanitizeHexColor($style['backgroundColor'] ?? '');
            // prevBgColor is only used for toggling between themes; keep but sanitize as well
            if (isset($style['prevBgColor'])) {
                $style['prevBgColor'] = $this->sanitizeHexColor($style['prevBgColor']);
            }
            $style['icon']['color'] = $this->sanitizeHexColor($style['icon']['color'] ?? '');
            $style['text']['color'] = $this->sanitizeHexColor($style['text']['color'] ?? '');

            $result[$key] = $style;
        }

        return $result;
    }

    /**
     * Validate and normalize HEX color values
     *
     * Rules:
     *  - Accepts strings in forms: "#RRGGBB", "RRGGBB", "#RGB", or "RGB" (case-insensitive)
     *  - Returns normalized uppercase value with leading '#'
     *  - Returns empty string for any non-matching value
     *
     * @param string $value
     * @return string
     */
    protected function sanitizeHexColor($value): string
    {
        if (!is_string($value)) {
            return '';
        }
        $value = trim((string)$value);
        if ($value === '') {
            return '';
        }
        // Remove optional leading '#'
        $hex = ltrim($value, '#');
        $len = strlen($hex);
        if ((ctype_xdigit($hex)) && ($len === 3 || $len === 6)) {
            return '#' . strtoupper($hex);
        }
        return '';
    }

    /**
     * Check if dropdown name is valid
     *
     * @return bool
     */
    protected function isValidDropdownName($name): bool
    {
        // Allow alphanumeric characters and underscores, 1 to 64 characters long
        $pattern = '/^[a-zA-Z][a-zA-Z0-9_]{0,63}$/';

        return preg_match($pattern, $name);
    }

    /**
     * Check if dropdown item name is valid
     *
     * @param $value
     * @return bool
     */
    protected function isValidDropdownItemName($value): bool
    {
        $pattern = '/^[A-Za-z0-9_\s]{0,63}$/';
        return preg_match($pattern, $value);
    }
}
