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

class GainsightPXApi extends SugarApi
{
    private const PROD_ENV_ID = 'AP-Y7AXIHT3YOTQ-2';
    private const NON_PROD_ENV_ID = 'AP-Y7AXIHT3YOTQ-2-3';

    /**
     * @inheritDoc
     */
    public function registerApiRest()
    {
        return [
            'gainsightEnvId' => [
                'reqType' => 'GET',
                'path' => ['gainsight', 'env-id'],
                'pathVars' => ['', ''],
                'method' => 'getEnvId',
                'shortHelp' => 'Returns Gainsight PX env ID based on host designation.',
            ],
        ];
    }

    /**
     * Return Gainsight env ID based on server host designation.
     *
     * @param ServiceBase $api
     * @param array $args
     * @return array
     */
    public function getEnvId(ServiceBase $api, array $args): array
    {
        $config = SugarConfig::getInstance();
        $analytics = $config->get('analytics', []);

        if (empty($analytics['enabled']) || ($analytics['connector'] ?? '') !== 'GainsightPX') {
            return ['gainsightKey' => ''];
        }

        $isProd = $config->get('host_designation') === 'production';

        return [
            'gainsightKey' => $isProd ? self::PROD_ENV_ID : self::NON_PROD_ENV_ID,
        ];
    }
}
