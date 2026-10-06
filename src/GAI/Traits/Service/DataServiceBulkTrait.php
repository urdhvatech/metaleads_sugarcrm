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

namespace Sugarcrm\Sugarcrm\GAI\Traits\Service;

use BeanFactory;
use SugarApiException;
use GuzzleHttp\Exception\GuzzleException;
use Sugarcrm\Sugarcrm\GAI\Adapter\AdapterFactory;
use Sugarcrm\Sugarcrm\GAI\Service;
use Sugarcrm\Sugarcrm\GAI\Exception\MaxTokenApiException;
use Sugarcrm\Sugarcrm\GAI\Exception\BackendNotConfiguredException;
use Sugarcrm\Sugarcrm\GAI\Exception\OtherSummarizationRunningException;
use Sugarcrm\Sugarcrm\GAI\Exception\InvalidGaiResponseException;
use Sugarcrm\Sugarcrm\GAI\Exception\EmptySummaryException;
use Sugarcrm\Sugarcrm\GAI\Client\Constants\GAIType;

trait DataServiceBulkTrait
{
    public function sendDataIngest(array $payload)
    {
        try {
            $service = new Service();
            $response = $service->sendDataIngest($payload);

            $expectedExceptionCode = [400, 401, 403, 404, 413, 500];
            $statusCode = $response->getStatusCode();

            if (in_array($statusCode, $expectedExceptionCode)) {
                switch ($statusCode) {
                    case 401:
                        throw new BackendNotConfiguredException();
                        break;
                    default:
                        $body = json_decode($response->getBody(), true);

                        $parentId = array_key_exists('objectType', $payload) ? $payload['objectType'] : '';
                        $parentModule = array_key_exists('objectId', $payload) ? $payload['objectId'] : '';

                        if (json_last_error() !== JSON_ERROR_NONE ||
                            !is_array($body) || !array_key_exists('errorMessage', $body)) {
                            $errorMessage = '[GAI][Summary][SendDataIngest][InvalidResponse][InvalidJson]: ' .
                            "Parent ID: {$parentId}, Parent Module: {$parentModule}, Error: " .
                            "Status Code: {$statusCode}, Error: " .
                            json_last_error_msg();

                            $GLOBALS['log']->error($errorMessage);

                            throw new InvalidGaiResponseException($errorMessage);
                        }

                        $errorMessage = $body['errorMessage'];

                        $GLOBALS['log']->error(
                            '[GAI][Summary][SendDataIngest][InvalidResponse]: ' .
                            "Parent ID: {$parentId}, Parent Module: {$parentModule}, Error: "
                        );

                        $GLOBALS['log']->error($errorMessage);
                        throw new InvalidGaiResponseException($errorMessage);
                        break;
                }
            }

            $responseData = json_decode($response->getBody(), true);

            return $responseData;
        } catch (GuzzleException $e) {
            throw $e;
        }
    }

    public function confirmDataSent(string $parentId, string $parentModule, $language, $translate = false)
    {
        $parentBean = BeanFactory::retrieveBean($parentModule, $parentId);
        $parentName = $parentBean->name;

        $adapterData = [
            'adapterType' => GAIType::GAI_DATA_INGEST,
            'usecaseType' => GAIType::GAI_SUMMARY,
            'objectType' => htmlspecialchars($parentModule, ENT_QUOTES, 'UTF-8'),
            'objectId' => htmlspecialchars($parentId, ENT_QUOTES, 'UTF-8'),
            'objectName' => htmlspecialchars($parentName, ENT_QUOTES, 'UTF-8'),
            'language' => $language,
        ];

        if ($translate) {
            $adapterData['translate'] = true;
        }

        $ingestionAdapter = (AdapterFactory::getDataAdapterInstance($adapterData));
        $payload = $ingestionAdapter->batchInference();

        try {
            $service = new Service();
            $response = $service->sendBatchInference($payload);

            $expectedExceptionCode = [400, 401, 402, 404, 409, 413, 500];
            $statusCode = $response->getStatusCode();

            if (in_array($statusCode, $expectedExceptionCode)) {
                switch ($statusCode) {
                    case 401:
                        throw new BackendNotConfiguredException();
                        break;
                    case 402:
                        throw new MaxTokenApiException();
                        break;
                    case 409:
                        throw new OtherSummarizationRunningException();
                        break;
                    default:
                        $body = json_decode($response->getBody(), true);

                        if (json_last_error() !== JSON_ERROR_NONE ||
                            !is_array($body) || !array_key_exists('errorMessage', $body)) {
                            $errorMessage = '[GAI][Summary][ConfirmIngestDataSent][InvalidResponse][InvalidJson]: ' .
                            "Parent ID: {$parentId}, Parent Module: {$parentModule}, Error: " .
                            "Status Code: {$statusCode}, Error: " .
                            json_last_error_msg();

                            throw new InvalidGaiResponseException($errorMessage);
                        }

                        $errorMessage = $body['errorMessage'];
                        throw new InvalidGaiResponseException($errorMessage);
                        break;
                }
            }

            $responseData = json_decode($response->getBody(), true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new InvalidGaiResponseException(
                    '[GAI][Summary][ConfirmIngestDataSent][InvalidResponse][InvalidJson]: ' .
                    "Parent ID: {$parentId}, Parent Module: {$parentModule}, Error: " .
                    json_last_error_msg()
                );
            }

            if (!is_array($responseData)) {
                throw new InvalidGaiResponseException(
                    '[GAI][Summary][ConfirmIngestDataSent][InvalidResponse][ResponseNotArray]: ' .
                    "Parent ID: {$parentId}, Parent Module: {$parentModule}, Error: " .
                    json_last_error_msg()
                );
            }

            if (!array_key_exists('evalId', $responseData)) {
                throw new InvalidGaiResponseException(
                    '[GAI][Summary][ConfirmIngestDataSent][InvalidResponse][MissingEvalId]: ' .
                    "Parent ID: {$parentId}, Parent Module: {$parentModule}, Error: " .
                    json_last_error_msg()
                );
            }

            return $responseData['evalId'];
        } catch (GuzzleException $e) {
            throw $e;
        }
    }
}
