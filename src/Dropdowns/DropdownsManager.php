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

namespace Sugarcrm\Sugarcrm\Dropdowns;

use MetaDataManager;
use Sugarcrm\Sugarcrm\Util\Files\FileLoader;
use SugarAutoLoader;

class DropdownsManager
{
    private const DD_CACHE_FILES = [
        "custom/include/DropdownsStyle/dropdowns_style.php",
        "custom/application/Ext/DropdownsStyle/dropdowns_style.ext.php"
    ];

    /**
     * Synchronize dropdowns style
     */
    public static function synchronizeDropdownsStyle()
    {
        self::cleanupDropdownsStyle();

        global $app_list_strings;
        $dirty = false;

        foreach ($app_list_strings as $domName => $options) {
            if (is_array($options) && !array_filter($options, 'is_array')) {
                $dropdownStyleUpdated = self::buildDropdownStyle($domName, $options, true, false);

                if ($dropdownStyleUpdated) {
                    $dirty = true;
                }
            }
        }

        if ($dirty) {
            self::rebuildDropdownsStyle();
        }
    }

    /**
     * Cleanup dropdowns style
     */
    public static function cleanupDropdownsStyle()
    {
        global $app_list_strings;
        $app_dropdowns_style = static::returnAppDropdownsStyle();

        $dirty = false;
        $suffix = '_style';

        if (!is_array($app_dropdowns_style)) {
            return;
        }

        foreach ($app_dropdowns_style as $styleKey => $styleData) {
            $dropdownKey = false;
            $stylePos = strpos($styleKey, $suffix);

            if ($stylePos !== false && $stylePos === strlen($styleKey) - strlen($suffix)) {
                $dropdownKey = substr($styleKey, 0, $stylePos);
            }

            if ($dropdownKey && !array_key_exists($dropdownKey, $app_list_strings)) {
                self::deleteDropdownStyle($styleKey);

                $dirty = true;
            }
        }

        if ($dirty) {
            self::rebuildDropdownsStyle();
        }
    }

    /**
     * Delete dropdown style
     *
     * @param string $styleKey
     */
    public static function deleteDropdownStyle(string $styleKey)
    {
        $extFrameworkMainPath = 'custom/Extension/application/Ext/DropdownsStyle/';
        $fileName = $extFrameworkMainPath . $styleKey . '.php';

        if (file_exists($fileName)) {
            $fileName = FileLoader::validateFilePath($fileName);

            unlink($fileName);
        }
    }

    /**
     * Build dropdown style
     *
     * @param string $dropdownName
     * @param array $dropdownValues
     * @param bool $strictUpdate
     */
    public static function buildDropdownStyle(
        string $dropdownName,
        array $dropdownValues,
        bool $strictUpdate = true,
        bool $rebuild = true
    ) {
        global $app_dropdowns_style;

        $dropdownStyle = [];
        $colorPalette = [
            '#517bf8', // @ocean
            '#36b0ff', // @pacific
            '#00e0e0', // @teal
            '#00ba83', // @green
            '#6cdf46', // @army
            '#ffd132', // @yellow
            '#ff9445', // @orange
            '#fa374f', // @red
            '#f476b1', // @coral
            '#cd74f2', // @pink
            '#8f5ff5', // @purple
            '#29388c', // @darkOcean
            '#145c95', // @darkPacific
            '#00636e', // @darkTeal
            '#056f37', // @darkGreen
            '#537600', // @darkArmy
            '#866500', // @darkYellow
            '#9b4617', // @darkOrange
            '#bb0e1b', // @darkRed
            '#a23354', // @darkCoral
            '#832a83', // @darkPink
            '#4c2d85', // @darkPurple
            '#c6ddff', // @lightOcean
            '#c0edff', // @lightPacific
            '#c5fffb', // @lightTeal
            '#baffcc', // @lightGreen
            '#e4fbb4', // @lightArmy
            '#fff7ad', // @lightYellow
            '#ffdebc', // @lightOrange
            '#ffd4d0', // @lightRed
            '#fde2eb', // @lightCoral
            '#f7d0fd', // @lightPink
            '#e2d4fd', // @lightPurple
        ];

        $dropdownKeys = array_keys($dropdownValues);
        $existingStyle = self::getDropdownStyle($dropdownName);
        $existingColors = self::getUsedDropdownColors($existingStyle, $dropdownKeys);
        $existingColors = self::reduceColors($existingColors, $colorPalette);
        $targetColors = array_values(array_diff($colorPalette, $existingColors));

        foreach ($dropdownKeys as $dropdownKey) {
            if (safeCount($targetColors) === 0) {
                $targetColors = $colorPalette;
            }

            $dropdownStyle[$dropdownKey] = array_key_exists($dropdownKey, $existingStyle) ?
                                    $existingStyle[$dropdownKey] :
                                    self::generateOptionStyle(array_shift($targetColors));
        }

        // Preserve the applyFormatting flag from existing style (custom or core)
        if (isset($existingStyle['applyFormatting'])) {
            $dropdownStyle['applyFormatting'] = $existingStyle['applyFormatting'];
        } else {
            // Check if core style has applyFormatting enabled
            $coreStyle = self::getCoreDropdownStyle($dropdownName);
            if (isset($coreStyle['applyFormatting'])) {
                $dropdownStyle['applyFormatting'] = $coreStyle['applyFormatting'];
            }
        }

        // we only updates what's changed
        if ($strictUpdate && self::stylesAreIdentical($existingStyle, $dropdownStyle)) {
            return false;
        }

        $dropdownStyleName = $dropdownName . '_style';
        $app_dropdowns_style[$dropdownStyleName] = $dropdownStyle;

        $contents = self::getExtensionContents($dropdownStyleName, $dropdownStyle);

        self::saveContents($dropdownName, $contents);

        if ($rebuild) {
            self::rebuildDropdownsStyle();
        }

        return true;
    }

    /**
     * Return app_dropdowns_style
     *
     * @return array
     */
    public static function returnAppDropdownsStyle()
    {
        $cacheKey = 'app_dropdowns_style';

        $cacheEntry = sugar_cache_retrieve($cacheKey);
        if (!empty($cacheEntry)) {
            return $cacheEntry;
        }

        global $app_dropdowns_style;

        $appDropdownsStyleOriginal = $app_dropdowns_style;

        // Load core dropdown styles
        $app_dropdowns_style = self::loadCoreDropdownStyles();

        // Load custom dropdown styles
        $files = SugarAutoLoader::existing(...self::DD_CACHE_FILES);

        foreach ($files as $file) {
            include $file;
        }

        $updatedDropdownsStyle = $app_dropdowns_style;
        $app_dropdowns_style = $appDropdownsStyleOriginal;

        return $updatedDropdownsStyle;
    }

    /**
     * Clear dropdowns style
     */
    public static function clearDropdownsStyle()
    {
        global $app_dropdowns_style;

        SugarAutoLoader::requireWithCustom('ModuleInstall/ModuleInstaller.php');
        $moduleInstallerClass = SugarAutoLoader::customClass('ModuleInstaller');

        $mi = new $moduleInstallerClass();
        $mi->silent = true;
        $mi->merge_files('Ext/DropdownsStyle/', 'dropdowns_style.ext.php', '', true, []);

        sugar_cache_reset();
        sugar_cache_reset_full();

        $mm = MetaDataManager::getManager();

        $mm->clearDropdownsStyleCache();

        // Trigger metadata refresh so dropdownsStyleUrl gets updated
        static::refreshMetadataCache();
    }

    /**
     * Refresh metadata cache for dropdown styles
     * Extracted to protected method for testability
     *
     * @return void
     */
    protected static function refreshMetadataCache()
    {
        MetaDataManager::refreshSectionCache(MetaDataManager::MM_CONFIG);
    }

    /**
     * Rebuild dropdowns style
     */
    public static function rebuildDropdownsStyle()
    {
        global $app_dropdowns_style;

        SugarAutoLoader::requireWithCustom('ModuleInstall/ModuleInstaller.php');
        $moduleInstallerClass = SugarAutoLoader::customClass('ModuleInstaller');

        $mi = new $moduleInstallerClass();
        $mi->silent = true;
        $mi->merge_files('Ext/DropdownsStyle/', 'dropdowns_style.ext.php', '', true, []);

        sugar_cache_reset();
        sugar_cache_reset_full();

        $files = SugarAutoLoader::existing(...self::DD_CACHE_FILES);

        foreach ($files as $file) {
            if (function_exists('opcache_invalidate')) {
                opcache_invalidate($file, true);
            }
        }

        $mm = MetaDataManager::getManager();

        $mm->clearDropdownsStyleCache();
        $mm->getDropdownsStyle();

        // Trigger metadata refresh so dropdownsStyleUrl gets updated
        static::refreshMetadataCache();

        $app_dropdowns_style = self::returnAppDropdownsStyle();
    }

    /**
     * Check if styles are identical
     *
     * @param array $oldStyle
     * @param array $newStyle
     * @return bool
     */
    public static function stylesAreIdentical(array $oldStyle, array $newStyle)
    {
        if (count($oldStyle) !== count($newStyle)) {
            return false;
        }

        sort($oldStyle);
        sort($newStyle);

        foreach ($oldStyle as $key => $value) {
            if (!array_key_exists($key, $newStyle)) {
                return false;
            }

            if (is_array($value) && is_array($newStyle[$key])) {
                if (!self::stylesAreIdentical($value, $newStyle[$key])) {
                    return false;
                }
            } elseif ($value !== $newStyle[$key]) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get extension content
     *
     * @param string $dropdownName
     * @param array $dropdown
     * @return string
     */
    public static function getExtensionContents($dropdownName, $dropdown)
    {
        $dropdownName = var_export($dropdownName, true);
        $contents = "<?php\n // created: " . date('Y-m-d H:i:s') . "\n";
        $contents .= "\n\$app_dropdowns_style[$dropdownName]=" . var_export($dropdown, true) . ';';

        return $contents;
    }

    /**
     * Save contents
     *
     * @param string $dropdownName
     * @param string $contents
     * @return bool
     */
    public static function saveContents($dropdownName, $contents)
    {
        global $log;
        $fileName = self::getExtensionFilePath($dropdownName);

        if ($fileName) {
            if (!check_file_name($fileName)) {
                return false;
            }

            if (file_put_contents($fileName, $contents) !== false) {
                return true;
            }
            $log->fatal("Unable to write edited dropdown style to file: $fileName");
        }
        return false;
    }

    /**
     * Get extension file path
     *
     * @param string $dropdownName
     * @return string|bool
     */
    public static function getExtensionFilePath($dropdownName)
    {
        global $log;

        $dropdownStyle = $dropdownName . '_style';
        $dirName = 'custom/Extension/application/Ext/DropdownsStyle';

        if (SugarAutoLoader::ensureDir($dirName)) {
            $fileName = "$dirName/$dropdownStyle.php";

            return $fileName;
        } else {
            $log->fatal("Unable to create dir: $dirName");
        }

        return false;
    }

    /**
     * Get dropdown style
     *
     * @param string $dropdownName
     * @return array
     */
    public static function getDropdownStyle(string $dropdownName): array
    {
        global $app_dropdowns_style;

        if (!$app_dropdowns_style) {
            return [];
        }

        $styleKey = $dropdownName . '_style';
        return array_key_exists($styleKey, $app_dropdowns_style) ? $app_dropdowns_style[$styleKey] : [];
    }

    /**
     * Load core (out-of-the-box) dropdown styles from core files
     *
     * This method loads the core dropdown style files and returns the styles array.
     * Used by multiple methods to avoid code duplication.
     *
     * @return array All core dropdown styles
     */
    protected static function loadCoreDropdownStyles(): array
    {
        $app_dropdowns_style = [];

        // Load core dropdown style files
        foreach (
            SugarAutoLoader::existing(
                "include/DropdownsStyle/dropdowns_style.php",
                "include/DropdownsStyle/dropdowns_style.override.php",
                "include/DropdownsStyle/dropdowns_style.php.override"
            ) as $file
        ) {
            include $file;
        }

        return $app_dropdowns_style;
    }

    /**
     * Get core (out-of-the-box) dropdown style for a specific dropdown
     *
     * This method checks if a dropdown has applyFormatting enabled by default
     * in core files. Used during synchronization to preserve formatting flags
     * after upgrade cleanup.
     *
     * @param string $dropdownName
     * @return array
     */
    protected static function getCoreDropdownStyle(string $dropdownName): array
    {
        $styleKey = $dropdownName . '_style';
        $coreStyles = self::loadCoreDropdownStyles();

        return $coreStyles[$styleKey] ?? [];
    }

    /**
     * Generate option style
     *
     * @param string $color
     * @return array
     */
    public static function generateOptionStyle(string $color): array
    {
        return [
            'backgroundColor' => $color,
        ];
    }

    /**
     * Get uniquely used dropdown colors
     *
     * @param array $colors
     * @param array $colorPalette
     *
     * @return array
     */
    public static function reduceColors($colors, $colorPalette): array
    {
        $usedUpColors = [];
        $pointer = 0;

        foreach ($colors as $color) {
            $idx = array_search($color, $colorPalette);

            if ($idx < $pointer) {
                $usedUpColors = [];
            }

            $usedUpColors[] = $color;
            $pointer = $idx;
        }

        return $usedUpColors;
    }

    /**
     * Get used dropdown colors
     *
     * @param array $dropdownStyle
     * @param array $dropdownKeys
     *
     * @return array
     */
    public static function getUsedDropdownColors(array $dropdownStyle, array $dropdownKeys): array
    {
        $colors = [];

        foreach ($dropdownStyle as $dropdownKey => $style) {
            if (!in_array($dropdownKey, $dropdownKeys)) {
                continue;
            }

            $colors[] = $style['backgroundColor'];
        }

        return $colors;
    }
}
