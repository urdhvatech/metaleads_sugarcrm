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

use SugarApiException;
use GuzzleHttp\Exception\GuzzleException;
use Sugarcrm\Sugarcrm\GAI\Service;
use Sugarcrm\Sugarcrm\GAI\Helper;
use Sugarcrm\Sugarcrm\GAI\Exception\MaxTokenApiException;
use Sugarcrm\Sugarcrm\GAI\Exception\BackendNotConfiguredException;
use Sugarcrm\Sugarcrm\GAI\Exception\EmptySummaryException;
use Sugarcrm\Sugarcrm\GAI\Client\Constants\GAIType;
use Sugarcrm\Sugarcrm\GAI\Constants\GAIConstants;
use Sugarcrm\Sugarcrm\GAI\Adapter\AdapterFactory;
use Sugarcrm\Sugarcrm\GAI\Traits\SummaryTrait;

trait DataServiceTrait
{
    use SummaryTrait;

    public function startGeneratingSummarizationProcess(array $payload)
    {
        try {
            $service = new Service();
            $response = $service->inference($payload);

            $expectedExceptionCode = [401, 402, 413, 500];
            $statusCode = $response->getStatusCode();

            if (in_array($statusCode, $expectedExceptionCode)) {
                switch ($statusCode) {
                    case 401:
                        throw new BackendNotConfiguredException();
                        break;
                    case 402:
                        throw new MaxTokenApiException();
                        break;
                    default:
                        $errorMessage = json_decode($response->getBody(), true)['errorMessage'];
                        throw new SugarApiException($errorMessage);
                        break;
                }
            }

            $responseData = \GuzzleHttp\json_decode($response->getBody(), true);

            return $responseData['evalId'];
        } catch (GuzzleException $e) {
            throw $e;
        }
    }

    /**
     * Retrieve the generated summarization from GAI Service
     *
     * @param array $payload
     *
     * @return array
     */
    public function retrieveGeneratedSummarization(array $payload)
    {
        try {
            $service = new Service();
            $response = $service->retrieve($payload);

            $expectedExceptionCode = [400, 401, 404, 402, 422, 500];
            $statusCode = $response->getStatusCode();

            if (in_array($statusCode, $expectedExceptionCode)) {
                switch ($statusCode) {
                    case 401:
                        throw new BackendNotConfiguredException();
                        break;
                    case 422:
                        throw new EmptySummaryException();
                        break;
                    default:
                        $errorMessage = json_decode($response->getBody(), true)['errorMessage'];
                        throw new SugarApiException($errorMessage);
                        break;
                }
            }

            $responseData = \GuzzleHttp\json_decode($response->getBody(), true);

            return $responseData;
        } catch (GuzzleException $e) {
            throw $e;
        }
    }

    /**
     * Prepare the payload then retrieve the summary from GAI Service
     *
     * @param string $evalId
     * @param string $usecaseType
     * @param string $adapterType
     *
     * @return array
     *
     * @throws EmptySummaryException
     * @throws GuzzleException
     * @throws SugarApiException
     */
    public function retrieveGAISummary(string $evalId, string $usecaseType, string $adapterType)
    {
        $data = [
            'evalId' => $evalId,
            'usecaseType' => $usecaseType,
            'adapterType' => $adapterType,
        ];

        $adapter = (AdapterFactory::getDataAdapterInstance($data));
        $payload = $adapter->getRetrieveData();

        return $this->retrieveGeneratedSummarization($payload);
    }

    /**
     * Check if the summary is ready on GAI Service
     *
     * @param string $evalId
     *
     * @return bool
     */
    public function isSummaryReadyOnGaiService(string $evalId): bool
    {
        $response = $this->retrieveGAISummary($evalId, 'gai_summary', 'gai_summary');

        if (array_key_exists('status', $response) && $response['status'] === GAIConstants::SUMZ_STATUS_COMPLETED) {
            return true;
        }

        return false;
    }


    /**
     * Determin the eval id for the current record
     *
     * @param string $parentId
     * @param string $parentModule
     * @param string $hash
     * @param string $originalEvalId
     * @param string $usecaseType
     * @param array $payload
     *
     * @return string
     */
    public function determineInferenceEvalId(
        string $parentId,
        string $parentModule,
        string $hash,
        string $originalEvalId,
        string $usecaseType,
        array $payload
    ) {
        $evalId = '';
        $currentUserLanguage = Helper::getCurrentUserPreferredLanguage();

        $currentRecord = $this->getSummarizationBean([
            'parent_id' => $parentId,
            'parent_module' => $parentModule,
            'hash' => $hash,
            'language' => $currentUserLanguage,
        ]);

        if (!$currentRecord) {
            $isTranslate = false;

            if ($originalEvalId) {
                //prepare the payload for translation
                $translationData = [
                    'language' => $currentUserLanguage,
                    'adapterType' => GAIType::GAI_INTERNALIZATION,
                    'parentObjectType' => $parentModule,
                    'originalEvalId' => $originalEvalId,
                    'usecaseType' => $usecaseType,
                ];

                $inferenceAdapter = (AdapterFactory::getDataAdapterInstance($translationData));
                $payload = $inferenceAdapter->getInferenceData();

                $isTranslate = true;
            } else {
                // Add user language to payload (not to hash)
                // Hash stays language-independent to reuse translations across languages with same content.
                $payload['contextData']['language'] = $currentUserLanguage;
            }

            $evalId = $this->startGeneratingSummarizationProcess($payload);

            $this->createSummarizationBean($parentId, $parentModule, $hash, $evalId, $isTranslate);
        } else {
            $evalId = $currentRecord->eval_id;
        }

        return $evalId;
    }
}
