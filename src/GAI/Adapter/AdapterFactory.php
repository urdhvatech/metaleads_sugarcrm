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

namespace Sugarcrm\Sugarcrm\GAI\Adapter;

use Exception;
use Sugarcrm\Sugarcrm\GAI\Client\Constants\GAIType;
use Sugarcrm\Sugarcrm\GAI\Adapter\Adapters\Data\GAISummaryDataAdapter;
use Sugarcrm\Sugarcrm\GAI\Adapter\Adapters\Data\GAIGenericDataAdapter;
use Sugarcrm\Sugarcrm\GAI\Adapter\Adapters\Data\GAIInternalizationAdapter;
use Sugarcrm\Sugarcrm\GAI\Adapter\Adapters\Data\GAIDataIngestAdapter;
use Sugarcrm\Sugarcrm\GAI\Adapter\Adapters\Config\GAIConfigSummaryAdapter;
use Sugarcrm\Sugarcrm\GAI\Adapter\Adapters\Config\GAITokenUsageAdapter;
use Sugarcrm\Sugarcrm\GAI\Configuration\ConfigurationOnPremAdapter;
use Sugarcrm\Sugarcrm\GAI\Configuration\ConfigurationOnDemandAdapter;
use Sugarcrm\Sugarcrm\GAI\Helper;

class AdapterFactory
{
    private const SOURCE_ID = 'ext_eapm_gai';
    private const ADAPTER_ON_DEMAND = 'on-demand';
    private const ADAPTER_ON_PREMISE = 'on-premise';

    /**
     * Returns an instance of the adapter based on gai usecase
     *
     * @param mixed $options
     * @return object|null
     */
    public static function getDataAdapterInstance($options)
    {
        $adapter = null;

        $adapterType = $options['adapterType'];
        $log = \LoggerManager::getLogger();

        switch ($adapterType) {
            case GAIType::GAI_SUMMARY:
                $adapter = new GAISummaryDataAdapter($options);
                break;
            case GAIType::GAI_GENERIC:
                $adapter = new GAIGenericDataAdapter($options);
                break;
            case GAIType::GAI_CONFIG:
                $adapter = new GAIConfigSummaryAdapter($options);
                break;
            case GAIType::GAI_TOKEN_USAGE:
                $adapter = new GAITokenUsageAdapter($options);
                break;
            case GAIType::GAI_INTERNALIZATION:
                $adapter = new GAIInternalizationAdapter($options);
                break;
            case GAIType::GAI_DATA_INGEST:
                $adapter = new GAIDataIngestAdapter($options);
                break;
        }

        if (!$adapter) {
            $log->error("GAI: Data adapter {$adapterType} does not exist.");

            throw new Exception("GAI: Data adapter {$adapterType} does not exist.");
        }

        return $adapter;
    }

    /**
     * Returns an instance of the configuration adapter based on the existance of OAuth2 credentials
     *
     * @param mixed $options
     * @return object|null
     */
    public static function getConfigAdapterInstance($options)
    {
        $properties = Helper::getConnectorProperties(self::SOURCE_ID);

        if (!empty($properties) && !empty($properties['oauth2_client_id']) && !empty($properties['oauth2_client_secret'])) {
            $adapterType = self::ADAPTER_ON_PREMISE;
        } else {
            $adapterType = self::ADAPTER_ON_DEMAND;
        }

        $adapter = null;

        $log = \LoggerManager::getLogger();

        switch ($adapterType) {
            case self::ADAPTER_ON_PREMISE:
                $adapter = new ConfigurationOnPremAdapter($options);
                break;
            case self::ADAPTER_ON_DEMAND:
                $adapter = new ConfigurationOnDemandAdapter($options);
                break;
        }

        if (!$adapter) {
            $log->error("GAI: Configuration adapter {$adapterType} does not exist.");

            throw new Exception("GAI: Configuration adapter {$adapterType} does not exist.");
        }

        return $adapter;
    }
}
