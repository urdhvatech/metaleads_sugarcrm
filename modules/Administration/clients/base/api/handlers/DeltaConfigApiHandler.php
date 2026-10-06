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

class DeltaConfigApiHandler extends ConfigApiHandler
{
    /**
     * Set the config value
     *
     * @param ServiceBase $api
     * @param array $args
     * @return array
     */
    public function setConfig(ServiceBase $api, array $args): array
    {
        $previousConfig = $this->getConfig($api, ['category' => 'delta']);

        if ($this->shouldContinue($previousConfig, $args)) {
            $args['delta_url'] = '';

            parent::setConfig($api, $args);
            $this->clearCache();
        }

        return $this->getConfig($api, ['category' => 'delta']);
    }

     /**
      * Check if we have to save the value and refresh cache for config
      * @param array $previousConfig
      * @param array $args
      * @return bool
      */
    private function shouldContinue(array $previousConfig, array $args): bool
    {
        foreach (['delta_modules_data', 'delta_enabled_modules'] as $key) {
            if (array_key_exists($key, $args)) {
                if (!array_key_exists($key, $previousConfig)) {
                    return true;
                }

                if ($previousConfig[$key] !== $args[$key]) {
                    return true;
                }
            }
        }

        return false;
    }
}
