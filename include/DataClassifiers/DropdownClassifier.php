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
 * Manages retrieving and updating saved dropdown classifications
 */
class DropdownClassifier
{
    /**
     * Path of the metadata file that store dropdown classifications
     */
    public const META_FILEPATH = 'Ext/DataClassifications/dropdown/dropdown_classifications.php';

    /**
     * Directory in which the custom Ext files are stored
     */
    public const EXT_FOLDER = 'custom/Extension/application/Ext/DataClassifications/dropdown';

    /**
     * Prefix of the custom Ext file name
     */
    public const EXT_FILENAME_PREFIX = 'dropdown_classifications';

    /**
     * Singleton instance holder
     *
     * @var DropdownClassifier
     */
    private static $instance = null;

    /**
     * Constructor marked private for singleton pattern
     */
    private function __construct()
    {
    }

    /**
     * Returns the singleton instance of this class
     *
     * @return DropdownClassifier
     */
    public static function getInstance() : DropdownClassifier
    {
        if (empty(static::$instance)) {
            static::$instance = new self;
        }
        return static::$instance;
    }

    /**
     * Gets the metadata file from the filesystem for consumption
     *
     * @return array
     */
    protected function getClassificationsFilesContent() : array
    {
        $app_list_strings_classifications = [];

        // Load OOB classifications
        SugarAutoLoader::requireWithCustom(self::META_FILEPATH);

        // Load custom classification extensions
        $extPath = SugarAutoLoader::loadExtension('dropdown_classifications');

        if (!empty($extPath)) {
            include $extPath;
        }

        return $app_list_strings_classifications;
    }

    /**
     * Returns the list of metadata for all classifications
     *
     * @return array All dropdown classification definitions
     */
    public function getClassifications() : array
    {
        return $this->getClassificationsFilesContent() ?? [];
    }

    /**
     * Returns the list of metadata for all classifications of a specific dropdown
     *
     * @param string $dropdownName The specific dropdown key
     * @return array The classifications definitions for the given dropdown
     */
    public function getClassificationsForDropdown(string $dropdownName) : array
    {
        return $this->getClassifications()[$dropdownName] ?? [];
    }

    /**
     * Updates the metadata for the given dropdown
     *
     * @param string $dropdownName The name of the dropdown
     * @param Array $classifications The updated list of metadata of all classifications for the dropdown
     * @return bool True if the save succeeded; False otherwise
     */
    public function saveClassifications(string $dropdownName, array $classifications) : bool
    {
        if (!SugarAutoLoader::ensureDir(self::EXT_FOLDER)) {
            $GLOBALS['log']->fatal('Unable to create dir: ' . self::EXT_FOLDER);
            return false;
        }

        return write_array_to_file_as_key_value_pair(
            "app_list_strings_classifications['" . basename($dropdownName) . "']",
            $classifications,
            $this->getExtensionFilePath($dropdownName)
        );
    }

    /**
     * Builds the proper file path for the file that stores custom extension data for the given dropdown
     *
     * @param string $dropdownName The name of the dropdown
     * @return string The extension file path
     */
    public function getExtensionFilePath(string $dropdownName) : string
    {
        return self::EXT_FOLDER . '/' . self::EXT_FILENAME_PREFIX . '_' . basename($dropdownName) . '.ext.php';
    }
}
