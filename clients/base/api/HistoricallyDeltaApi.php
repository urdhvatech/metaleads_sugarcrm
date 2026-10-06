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

use Sugarcrm\Sugarcrm\HistoricallyDelta\Logger;
use Sugarcrm\Sugarcrm\HistoricallyDelta\Client\DeltaClient;
use Sugarcrm\Sugarcrm\HistoricallyDelta\Helper;
use Sugarcrm\Sugarcrm\HistoricallyDelta\Decorators\FieldComparisonDecorator;

class HistoricallyDeltaApi extends FilterApi
{
    /**
     * @inheritdoc
     */
    public function registerApiRest()
    {
        return [
            'retrieveHistoricallyDelta' => [
                'reqType' => 'POST',
                'path' => ['historically', 'delta'],
                'pathVars' => ['', ''],
                'method' => 'retrieveHistoricallyDelta',
                'shortHelp' => 'Retrieve the delta of the targeted module',
                'longHelp' => 'include/api/help/historically_delta_retrieve_data.html',
                'minVersion' => '11.23',
            ],
        ];
    }

    /**
     * Retrieve the delta of the opportunities.
     * @param \ServiceBase $api
     * @param array $args
     * @return array
     */
    public function retrieveHistoricallyDelta(ServiceBase $api, array $args)
    {
        if (!hasHistoricallyDeltaLicense()) {
            throw new SugarApiExceptionNotAuthorized(translate('LBL_HISTORICALLY_DELTA_NO_LICENSE_ACCESS'));
        }

        $this->requireArgs($args, ['recordIds', 'module', 'deltaDate']);

        $module = $args['module'];
        $recordIds = $args['recordIds'];
        $deltaDate = $args['deltaDate'];

        $config = Helper::getDeltaConfig($module);
        $logger = new Logger(LoggerManager::getLogger());

        $isEnabled = $config['isEnabled'];
        $fields = $config['enabledFields'];

        if (!$isEnabled) {
            throw new SugarApiExceptionError("No fields are enabled for Historically Delta in the $module module");
        }

        if (empty($fields)) {
            throw new SugarApiExceptionError("Historically Delta is not enabled for this module: $module");
        }

        $historicallyDeltaClient = new DeltaClient(
            $module,
            $recordIds,
            $fields,
            $deltaDate
        );

        $deltaData = [];

        try {
            $deltaData = $historicallyDeltaClient->retrieveDelta();
        } catch (Exception $e) {
            $logger->alert('Historically Delta: unable to retrieve delta' . $module . ' ' . $e->getMessage());

            throw new SugarApiExceptionError(
                'Historically Delta: unable to retrieve delta' . $module . ' ' . $e->getMessage()
            );
        } catch (InvalidArgumentException $e) {
            $logger->alert('Historically Delta: unable to retrieve delta' . $module . ' ' . $e->getMessage());

            throw new SugarApiExceptionError(
                'Historically Delta: unable to retrieve delta' . $module . ' ' . $e->getMessage()
            );
        } catch (RuntimeException $e) {
            $logger->alert('Historically Delta: unable to retrieve delta' . $module . ' ' . $e->getMessage());

            throw new SugarApiExceptionError(
                'Historically Delta: unable to retrieve delta' . $module . ' ' . $e->getMessage()
            );
        }


        if (!is_array($deltaData) || empty($deltaData)) {
            return [];
        }

        $decorator = new FieldComparisonDecorator();
        $response = $decorator->decorate($deltaData, $args['module']);

        return $response;
    }
}
