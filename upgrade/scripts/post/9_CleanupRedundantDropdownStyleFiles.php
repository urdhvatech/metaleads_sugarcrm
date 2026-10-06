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

use Sugarcrm\Sugarcrm\Dropdowns\DropdownsManager;
use Sugarcrm\Sugarcrm\Util\Files\FileLoader;

/**
 * Cleanup redundant dropdown style files to enable new core color palette
 * when no actual customizations exist
 *
 * Processes ALL dropdown styles in the system to remove redundant files from 25.2.0.
 * Only removes basic color-only files while preserving genuine customizations.
 */
class SugarUpgradeCleanupRedundantDropdownStyleFiles extends UpgradeScript
{
    public $order = 9600; // Run after the original sync script
    public $type = self::UPGRADE_DB;

    /**
     * Cache for core dropdown options to avoid repeated file loading
     * @var array|null
     */
    private $coreDropdownsCache = null;

    public function run()
    {
        $this->log('Running CleanupRedundantDropdownStyleFiles script...');
        // Only run for upgrades from versions that had the old color system
        if (version_compare($this->from_version, '25.2.0', '<')) {
            $this->refreshDropdownStyleColors();
        } else {
            $this->log('Not cleaning up dropdown style files - version check failed');
        }
    }

    /**
     * Cleanup redundant dropdown style files by intelligently removing basic custom files
     * Processes all dropdown styles in the system
     */
    private function refreshDropdownStyleColors()
    {
        $this->log('Analyzing ALL dropdown style files for cleanup...');
        // Load current dropdown lists - ensure they're properly initialized
        $appListStrings = $this->getDropdownLists();
        if (empty($appListStrings)) {
            $this->log('No dropdown lists found - nothing to do');
            return;
        }
        $this->log('Successfully loaded ' . count($appListStrings) . ' dropdown lists');

        // Load core dropdown options once before processing
        $this->loadCoreDropdownsCache();

        $filesRemoved = 0;
        $filesAnalyzed = 0;

        // Process all dropdown styles - iterate through all dropdowns in app_list_strings
        foreach ($appListStrings as $domName => $options) {
            if (!is_array($options) || array_filter($options, 'is_array')) {
                continue; // Skip non-dropdown arrays
            }
            $styleKey = $domName . '_style';
            $filesAnalyzed++;
            if ($this->shouldRemoveCustomStyleFile($styleKey, $domName, $options)) {
                if ($this->removeCustomStyleFile($styleKey)) {
                    $filesRemoved++;
                    $this->log("✓ Removed basic color-only custom file: {$styleKey}");
                } else {
                    $this->log("✗ Failed to remove custom file: {$styleKey}");
                }
            } else {
                $this->log("→ Keeping custom file: {$styleKey} (has customizations or different structure)");
            }
        }
        $this->log("Analyzed {$filesAnalyzed} dropdown styles, removed {$filesRemoved} basic color-only files");
        if ($filesRemoved > 0) {
            // Rebuild dropdown styles to pick up new core colors
            $this->log('Rebuilding dropdown styles to apply new core colors...');
            DropdownsManager::rebuildDropdownsStyle();
            $this->log('✓ Successfully refreshed dropdown style colors');
        } else {
            $this->log('→ No custom dropdown style files removed - no color refresh needed');
        }
    }

    /**
     * Get dropdown lists reliably during upgrade
     * @return array
     */
    private function getDropdownLists()
    {
        // Try the standard function first
        $appListStrings = return_app_list_strings_language('en_us', false);
        if (!empty($appListStrings)) {
            return $appListStrings;
        }

        return [];
    }

    /**
     * Load and cache core dropdown options to avoid repeated file access
     * This method loads the core language file once and caches the result
     */
    private function loadCoreDropdownsCache()
    {
        if ($this->coreDropdownsCache !== null) {
            return; // Already loaded
        }

        $this->log('Loading core dropdown options cache...');

        // Use the same logic as getCoreDropdownOptions but cache the result
        $app_list_strings = [];

        $coreFile = 'include/language/en_us.lang.php';
        try {
            include FileLoader::validateFilePath($coreFile);
            $this->log("Loaded core language file for cache: {$coreFile}");
        } catch (Exception $e) {
            $this->log("Error loading core language file {$coreFile}: " . $e->getMessage());
        }

        // Cache the loaded dropdown options (or empty array if nothing loaded)
        $this->coreDropdownsCache = $app_list_strings;

        if (empty($this->coreDropdownsCache)) {
            $this->log("Warning: No core language files could be loaded for cache");
        } else {
            $this->log("Successfully cached " . count($this->coreDropdownsCache) . " core dropdown definitions");
        }
    }

    /**
     * Get core/out-of-the-box dropdown options for comparison
     * Uses cached core dropdown data (loaded once before processing loop)
     *
     * @param string $domName The dropdown name (e.g., 'sales_stage_dom')
     * @return array Core dropdown options
     */
    protected function getCoreDropdownOptions($domName)
    {
        // Use cached data (should be loaded by loadCoreDropdownsCache before this is called)
        if ($this->coreDropdownsCache === null) {
            $this->log("Warning: Core dropdown cache not loaded, loading now...");
            $this->loadCoreDropdownsCache();
        }

        // If cache is empty, we cannot safely determine if a dropdown was customized
        if (empty($this->coreDropdownsCache)) {
            $this->log("No core dropdown data available - keeping custom style file for safety");
            return []; // Return empty to trigger "keep file" logic
        }

        // Return the core dropdown options if they exist
        $coreOptions = $this->coreDropdownsCache[$domName] ?? [];

        if (empty($coreOptions)) {
            $this->log("No core dropdown options found for {$domName}");
        } else {
            $this->log("Found " . count($coreOptions) . " core options for {$domName}");
        }

        return $coreOptions;
    }

    /**
     * Convert style key to dropdown name
     * e.g., 'sales_stage_dom_style' -> 'sales_stage_dom'
     *
     * @param string $styleKey
     * @return string
     */
    private function getDropdownNameFromStyleKey($styleKey)
    {
        // Remove '_style' suffix to get the dropdown name
        return preg_replace('/_style$/', '', $styleKey);
    }

    /**
     * Check if we should remove the custom style file
     *
     * @param string $styleKey The style key (e.g., 'sales_stage_dom_style')
     * @param string $domName The dropdown name (e.g., 'sales_stage_dom')
     * @param array $currentOptions Current dropdown options (may include customizations)
     * @return bool
     */
    private function shouldRemoveCustomStyleFile($styleKey, $domName, $currentOptions)
    {
        // Check if custom file exists
        $customFilePath = "custom/Extension/application/Ext/DropdownsStyle/{$styleKey}.php";
        if (!file_exists($customFilePath)) {
            return false; // No custom file exists
        }
        // Load the custom style
        $customStyle = $this->loadCustomStyle($customFilePath, $styleKey);
        if (empty($customStyle)) {
            $this->log("Custom style file {$styleKey} is empty or invalid - safe to remove");
            return true; // Empty or invalid custom file, safe to remove
        }
        // Get the core/out-of-the-box dropdown options for comparison
        $coreOptions = $this->getCoreDropdownOptions($domName);
        if (empty($coreOptions)) {
            $this->log("Custom style {$styleKey} - no core dropdown found - keeping");
            return false; // No core dropdown to compare against, keep custom
        }

        // Check if the custom style keys match the CORE dropdown options
        // If current dropdown has more options than core, it means user added custom options
        $customStyleKeys = array_keys($customStyle);
        $coreKeys = array_keys($coreOptions);

        // Filter out system-added keys that should be ignored in comparison
        $systemKeys = ['applyFormatting'];
        $customStyleKeys = array_diff($customStyleKeys, $systemKeys);
        $coreKeys = array_diff($coreKeys, $systemKeys);

        sort($customStyleKeys);
        sort($coreKeys);

        if ($customStyleKeys !== $coreKeys) {
            $this->log("Custom style {$styleKey} has different options than core dropdown - keeping (likely has custom dropdown options)");
            $this->log("Custom keys: " . implode(', ', $customStyleKeys));
            $this->log("Core keys: " . implode(', ', $coreKeys));
            return false; // Different options than core, keep custom
        }

        $this->log("Custom style {$styleKey} matches core dropdown options - safe to remove");
        return true; // Safe to refresh from core
    }

    /**
     * Load custom style from file
     *
     * @param string $filePath Path to the custom style file
     * @param string $styleKey The style key to extract
     * @return array
     */
    private function loadCustomStyle($filePath, $styleKey)
    {
        $app_dropdowns_style = [];

        try {
            // Safely include the file
            include $filePath;
            return $app_dropdowns_style[$styleKey] ?? [];
        } catch (Exception $e) {
            $this->log("Error loading custom style from {$filePath}: " . $e->getMessage());
            return [];
        } catch (ParseError $e) {
            $this->log("Parse error in custom style file {$filePath}: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Remove the custom style file
     *
     * @param string $styleKey The style key
     * @return bool Success status
     */
    private function removeCustomStyleFile($styleKey)
    {
        $customFilePath = "custom/Extension/application/Ext/DropdownsStyle/{$styleKey}.php";
        if (!file_exists($customFilePath)) {
            return false;
        }
        try {
            // Remove the custom style file directly
            if (unlink($customFilePath)) {
                $this->log("Successfully removed: {$customFilePath}");
                return true;
            } else {
                $this->log("Failed to remove file: {$customFilePath}");
                return false;
            }
        } catch (Exception $e) {
            $this->log("Error removing file {$customFilePath}: " . $e->getMessage());
            return false;
        }
    }
}
