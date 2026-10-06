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

namespace Sugarcrm\Sugarcrm\HistoricallyDelta;

use Administration;
use DateInterval;

class Helper
{
    /**
     * Get the enabled fields for the module.
     *
     * @param string $module
     */
    public static function getDeltaConfig(string $module): array
    {
        $config = [
            'isEnabled' => false,
            'enabledFields' => [],
        ];

        $administration = new Administration();
        $administration->retrieveSettings('delta', false);
        $historicallyDelta = [];

        if (!empty($administration->settings['delta_modules_data'])) {
            $historicallyDelta['modulesData'] = $administration->settings['delta_modules_data'];
        }

        if (!empty($administration->settings['delta_enabled_modules'])) {
            $historicallyDelta['enabled_modules'] = $administration->settings['delta_enabled_modules'];
        }

        if (empty($historicallyDelta['enabled_modules']) || empty($historicallyDelta['modulesData'])) {
            return $config;
        }

        if (!in_array($module, $historicallyDelta['enabled_modules'])) {
            return $config;
        }

        if (!array_key_exists($module, $historicallyDelta['modulesData'])) {
            return $config;
        }

        $moduleData = $historicallyDelta['modulesData'][$module];

        if (!is_array($moduleData)) {
            return $config;
        }

        $configuredFields = [];

        foreach ($moduleData as $fieldName => $fieldData) {
            if (empty($fieldData['enabled'])) {
                continue;
            }

            $configuredFields[] = $fieldName;
        }

        $config['isEnabled'] = true;
        $config['enabledFields'] = $configuredFields;

        return $config;
    }

    /**
     * Get the interval for the delta date.
     */
    public static function getInterval(string $interval): DateInterval
    {
        $intervals = [
            '7_days' => new DateInterval('P7D'),
            '14_days' => new DateInterval('P14D'),
            '30_days' => new DateInterval('P30D'),
        ];

        return $intervals[$interval] ?? new DateInterval('P7D');
    }
}
