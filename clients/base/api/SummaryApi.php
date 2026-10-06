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

use Sugarcrm\Sugarcrm\GAI\Helper;
use Sugarcrm\Sugarcrm\GAI\Adapter\AdapterFactory;
use Sugarcrm\Sugarcrm\GAI\Client\Constants\GAIType;
use Sugarcrm\Sugarcrm\GAI\Constants\GAIConstants;
use Sugarcrm\Sugarcrm\GAI\Traits\DataStructure\DataStructureBulkTrait;
use Sugarcrm\Sugarcrm\GAI\Traits\Service\DataServiceBulkTrait;
use Sugarcrm\Sugarcrm\GAI\Traits\Service\DataServiceTrait;
use Sugarcrm\Sugarcrm\GAI\Traits\DataBackgroundBulkTrait;
use Sugarcrm\Sugarcrm\GAI\Traits\NotificationTrait;
use Sugarcrm\Sugarcrm\GAI\Exception\MaxTokenApiException;
use Sugarcrm\Sugarcrm\GAI\Exception\BackendNotConfiguredException;
use Sugarcrm\Sugarcrm\GAI\Exception\EmptySummaryException;

class SummaryApi extends SugarApi
{
    public function registerApiRest()
    {
        return [
            'infer' => [
                'reqType' => 'POST',
                'path' => ['<module>', '?', 'intelligence', 'summary', 'infer'],
                'pathVars' => ['module', 'recordId', '', '', ''],
                'method' => 'infer',
                'shortHelp' => 'Performs inference on a record',
                'longHelp' => 'include/api/help/intelligence_summary_infer_post_help.html',
            ],
            'ingest' => [
                'reqType' => 'POST',
                'path' => ['<module>', '?', 'intelligence', 'summary', 'ingest'],
                'pathVars' => ['module', 'recordId', '', '', ''],
                'method' => 'ingest',
                'shortHelp' => 'Ingests summary data for bulk processing',
                'longHelp' => 'include/api/help/intelligence_summary_ingest_data_post_help.html',
            ],
            'retrieve' => [
                'reqType' => 'GET',
                'path' => ['<module>', '?', 'intelligence', 'summary', 'retrieve'],
                'pathVars' => ['module', 'recordId', '', '', ''],
                'method' => 'retrieve',
                'shortHelp' => 'Retrieves saved summarization details for a record',
                'longHelp' => 'include/api/help/intelligence_summary_retrieve_get_help.html',
            ],
            'retrieveByEvalId' => [
                'reqType' => 'GET',
                'path' => ['<module>', '?', 'intelligence', 'summary', 'retrieve', '?'],
                'pathVars' => ['module', 'recordId', '', '', '', 'evalId'],
                'method' => 'retrieveByEvalId',
                'shortHelp' => 'Retrieves summarization result using evalId',
                'longHelp' => 'include/api/help/intelligence_summary_retrieve_evald_get_help.html.html',
            ],
            'translate' => [
                'reqType' => 'POST',
                'path' => ['<module>', '?', 'intelligence', 'summary', 'translate'],
                'pathVars' => ['module', 'recordId', '', '', ''],
                'method' => 'translate',
                'shortHelp' => 'Generates or fetches translated summary for a record',
                'longHelp' => 'include/api/help/intelligence_summary_translate_post_help.html',
            ],
            'usageToken' => [
                'reqType' => 'GET',
                'path' => ['service', 'intelligence', 'usage', 'token'],
                'pathVars' => ['', '', '', ''],
                'method' => 'getUsageToken',
                'shortHelp' => 'Retrieves current usage token statistics',
                'longHelp' => 'include/api/help/intelligence_summary_usage_token_get_help.html',
            ],
        ];
    }

    use DataServiceBulkTrait, DataServiceTrait, DataBackgroundBulkTrait, DataStructureBulkTrait, NotificationTrait;

    private static $gaiConfig = null;
    private static $gaiBulkConfig = null;

    /**
     * Inference call for modules that do not require bulk processing.
     *
     * @param ServiceBase $api
     * @param array $args
     * @return array
     */
    public function infer(ServiceBase $api, array $args): array
    {
        if (!hasGaiLicense()) {
            throw new SugarApiExceptionNotAuthorized(translate('LBL_GAI_NO_LICENSE_ACCESS'));
        }

        if (!ACLController::checkAccess('SummarizationGai', 'view')) {
            throw new SugarApiExceptionNotAuthorized('LBL_GAI_ERROR_NO_ACCESS');
        }

        $this->requireArgs($args, ['module', 'id']);

        $parentId = $args['id'];
        $parentModule = $args['module'];
        $currentUserLanguage = Helper::getCurrentUserPreferredLanguage();

        try {
            $evalId = '';
            $originalEvalId = ''; // Check if we have a translation for a specific hash, regardless of the language.
            $status = GAIConstants::SUMZ_STATUS_COMPLETED;

            $usecaseType = GAIType::GAI_SUMMARY;

            $configData = [
                'parentObjectType' => $parentModule,
                'usecaseType' => $usecaseType,
                'adapterType' => GAIType::GAI_CONFIG,
            ];

            $configAdapter = AdapterFactory::getDataAdapterInstance($configData);
            $configPayload = $configAdapter->getConfigData();

            $configDataResp = Helper::getConfig($configPayload, self::$gaiConfig);
            $config = Helper::resolveConfig($configDataResp, $usecaseType);

            self::$gaiConfig = $config;

            $inferenceData = [...$args, ...$config, ...['adapterType' => $usecaseType, 'usecaseType' => $usecaseType]];

            $inferenceAdapter = (AdapterFactory::getDataAdapterInstance($inferenceData));
            $payload = $inferenceAdapter->getInferenceData();

            $hash = md5(json_encode($payload));

            // Check if we have a summary for a specific hash, regardless of the language.
            $existingSummaryForHash = $this->getSummarizationBean([
                'parent_id' => $parentId,
                'parent_module' => $parentModule,
                'hash' => $hash,
                'is_translate' => false,
            ]);

            if ($existingSummaryForHash) {
                $originalEvalId = $existingSummaryForHash->eval_id;
            }

            $savedSummary = $this->getSavedSummarizationDetails([
                'parent_id' => $parentId,
                'parent_module' => $parentModule,
                'language' => $currentUserLanguage,
            ]);

            $evalId = $this->determineInferenceEvalId(
                $parentId,
                $parentModule,
                $hash,
                $originalEvalId,
                $usecaseType,
                $payload
            );

            $response = [
                'evalId' => $evalId,
                'status' => $status,
            ];

            $savedSummary && $response += [
                'summary' => $savedSummary['summary'],
                'dateModified' => $savedSummary['dateModified'],
            ];

            return $response;
        } catch (Error|Exception $error) {
            $statusCode = Helper::getStatusCodeFromError($error);

            switch ($statusCode) {
                case 401:
                    throw new BackendNotConfiguredException();
                    break;
                case 402:
                    throw new MaxTokenApiException();
                    break;
                default:
                    $errorMessage = $error->getMessage();
                    throw new SugarApiException($errorMessage);
                    break;
            }
        }
    }

    /**
     * Ingest data call for modules that require bulk processing.
     *
     * @param ServiceBase $api
     * @param array $args
     *
     * @return array
     */
    public function ingest(ServiceBase $api, array $args): array
    {
        if (!hasGaiLicense()) {
            throw new SugarApiExceptionNotAuthorized(translate('LBL_GAI_NO_LICENSE_ACCESS'));
        }

        if (!ACLController::checkAccess('SummarizationGai', 'view')) {
            throw new SugarApiExceptionNotAuthorized('LBL_GAI_ERROR_NO_ACCESS');
        }

        $this->requireArgs($args, ['module', 'recordId']);

        global $current_user;

        $parentId = $args['recordId'];
        $parentModule = $args['module'];

        if (array_key_exists('force', $args) && filter_var($args['force'], FILTER_VALIDATE_BOOLEAN)) {
            $this->forceIngestDataForDelta($api, $args);
        }

        $retrieveSummaryCriteria = $this->getSummaryCriteriaWithParams([
            ['field' => 'is_translate', 'operator' => 'equals', 'value' => false],
        ]);

        $summaryBean = $this->retrieveSummaryBeanByRelateRecord(
            $parentId,
            $parentModule,
            $retrieveSummaryCriteria
        );

        $currentUserLanguage = Helper::getCurrentUserPreferredLanguage();
        $useBackgroundIngest = true;
        $changesDetected = false;

        $isFirstIngest = !($summaryBean instanceof SugarBean);
        $isBeaninStalledState = false;

        $oldSummary = null;
        $newSummaryBean = null;
        $stalledBean = false;

        if (!$isFirstIngest && (
            Helper::isBeanInStalledState($summaryBean, GAIConstants::SUMZ_STATUS_READY_FOR_INGEST, '+96 hour')
            || Helper::isBeanInStalledState($summaryBean, GAIConstants::SUMZ_STATUS_PENDING, '+2 hour')
        )) {
            //If the bean becomes stuck during processing
            //due to an issue with the scheduled job, it will to remove it and start the process again.
            $isBeaninStalledState = true;
            $stalledBean = $summaryBean;

            $isFirstIngest = true;
        }

        if ($isFirstIngest) {
            $summaryBean = $this->createSummaryBean(
                $parentId,
                $parentModule,
                GAIConstants::SUMZ_STATUS_READY_FOR_INGEST,
                $currentUserLanguage
            );

            if ($isBeaninStalledState && ($stalledBean instanceof SugarBean)) {
                // Delete the stalled bean only after creating the new one
                // to avoid race conditions from other users or UI requests
                $stalledBean->error_message = json_encode(['errorMessage' => 'Bean in Stalled State']);
                $stalledBean->deleted = 1;
                $stalledBean->processed = true;
                $stalledBean->save();
            }

            if ($useBackgroundIngest) {
                $this->createIngestScheduler($parentId, $parentModule, $current_user->id);

                return $this->deltaResponseReadyForIngest($oldSummary);
            }

            try {
                if (!self::$gaiBulkConfig) {
                    self::$gaiBulkConfig = Helper::resolveConfigBulk($parentModule);
                }

                $summaryBean->status = GAIConstants::SUMZ_STATUS_PENDING;
                $summaryBean->processed = true;
                $summaryBean->save();

                $this->ingestData(
                    $parentId,
                    $parentModule,
                    $parentId,
                    self::$gaiBulkConfig,
                    true,
                    $changesDetected,
                    null,
                    null
                );
            } catch (Exception $e) {
                return $this->failedToIngestInitial($summaryBean, $e);
            }
        } else {
            $newSummaryBean = null;

            try {
                if (!self::$gaiBulkConfig) {
                    self::$gaiBulkConfig = Helper::resolveConfigBulk($parentModule);
                }

                $this->processDeltaIngest(
                    $parentId,
                    $parentModule,
                    self::$gaiBulkConfig,
                    $summaryBean,
                    $changesDetected,
                    $oldSummary,
                    $newSummaryBean
                );
            } catch (Exception $e) {
                return $this->handleIngestState($e, $summaryBean, $oldSummary, $newSummaryBean);
            }
        }

        if (!$changesDetected) {
            return $this->noChangesDetected($isFirstIngest, $summaryBean, $newSummaryBean);
        }

        if (!$isFirstIngest) {
            // On delta ingestion, update summaryBean to reflect the newly created summary
            // and ensure correct data association
            $summaryBean = $newSummaryBean;
        }

        try {
            $translate = false;
            $language = $summaryBean->language;
            $confirmation = $this->confirmIngestDataSent(
                $summaryBean,
                $parentId,
                $parentModule,
                $language,
                $translate,
                $oldSummary
            );

            return $confirmation;
        } catch (Exception $e) {
            return $this->failedConfirmDataSent($summaryBean, $e, $oldSummary);
        }
    }

    /**
     * The retrieve call based on a eval_id
     *
     * @param ServiceBase $api
     * @param array $args
     * @return array
     */
    public function retrieveByEvalId(ServiceBase $api, array $args): array
    {
        if (!hasGaiLicense()) {
            throw new SugarApiExceptionNotAuthorized(translate('LBL_GAI_NO_LICENSE_ACCESS'));
        }

        if (!ACLController::checkAccess('SummarizationGai', 'view')) {
            throw new SugarApiExceptionNotAuthorized('LBL_GAI_ERROR_NO_ACCESS');
        }

        $this->requireArgs($args, ['evalId']);

        $evalId = $args['evalId'];
        $usecaseType = GAIType::GAI_SUMMARY;

        try {
            $gaiBean = $this->getSummarizationBean(['eval_id' => $evalId]);

            if (!$gaiBean) {
                throw new SugarApiExceptionNotFound();
            }

            $currentRecord = $this->formatBean($api, [], $gaiBean);

            if ($currentRecord['status'] === GAIConstants::SUMZ_STATUS_COMPLETED) {
                return [
                    'evalId' => $evalId,
                    'status' => GAIConstants::SUMZ_STATUS_COMPLETED,
                    'data' => $currentRecord,
                ];
            }

            $response = $this->retrieveGAISummary($evalId, $usecaseType, $usecaseType);

            if (array_key_exists('status', $response) && $response['status'] === GAIConstants::SUMZ_STATUS_PROCESSING) {
                $data = [
                    'evalId' => $evalId,
                    'usecaseType' => $usecaseType,
                    'status' => GAIConstants::SUMZ_STATUS_PROCESSING,
                ];

                return $data;
            }

            $sumzBean = $this->updateSummarization($response, $gaiBean);

            $data = $this->formatBean($api, [], $sumzBean);

            Helper::cleanupOldSuccessSummary(
                $sumzBean->parent_id,
                $sumzBean->parent_module,
                $sumzBean->id,
                $sumzBean->language
            );

            $currentUserLanguage = Helper::getCurrentUserPreferredLanguage();

            return [
                'evalId' => $evalId,
                'status' => GAIConstants::SUMZ_STATUS_COMPLETED,
                'data' => $data,
                'currentLanguage' => $currentUserLanguage,
            ];
        } catch (Error|Exception $error) {
            $GLOBALS['log']->error("[GAI][Retrieve][$evalId]: " . $error->getMessage());

            $statusCode = Helper::getStatusCodeFromError($error);
            $errorMessage = Helper::getErrorMessageFromError($error);

            if ($evalId) {
                // If GAI service fails, delete bean by evalID to restart the process from scratch next time
                $this->deleteSummarizationByEvalId($evalId, $errorMessage);
            }

            switch ($statusCode) {
                case 422:
                    throw new EmptySummaryException();
                    break;
                case 401:
                    throw new BackendNotConfiguredException();
                    break;
                case 404:
                    throw new SugarApiExceptionNotFound("EvalID not found: $evalId");
                    break;
                default:
                    $errorMessage = $error->getMessage();
                    throw new SugarApiExceptionError($errorMessage);
                    break;
            }
        }
    }

    /**
     * Fetches the saved summarization details for a specific record and module.
     *
     * @param ServiceBase $api
     * @param array $args
     * @return array
     */
    public function retrieve(ServiceBase $api, array $args): array
    {
        if (!hasGaiLicense()) {
            throw new SugarApiExceptionNotAuthorized(translate('LBL_GAI_NO_LICENSE_ACCESS'));
        }

        if (!ACLController::checkAccess('SummarizationGai', 'view')) {
            throw new SugarApiExceptionNotAuthorized('LBL_GAI_ERROR_NO_ACCESS');
        }

        $this->requireArgs($args, ['module', 'recordId']);

        $parentId = $args['recordId'];
        $parentModule = $args['module'];
        $currentUserLanguage = isset($args['language']) ? $args['language'] : Helper::getCurrentUserPreferredLanguage();

        try {
            $summaryData = $this->getSavedSummarizationDetails([
                'parent_id' => $parentId,
                'parent_module' => $parentModule,
                'language' => $currentUserLanguage,
                'status' => GAIConstants::SUMZ_STATUS_COMPLETED,
            ]);

            if ($summaryData === false) {
                $summaryData = [
                    'summary' => null,
                    'dateModified' => null,
                ];
            }

            return $summaryData;
        } catch (Error|Exception $error) {
            $errorMessage = $error->getMessage();

            throw new SugarApiExceptionError($errorMessage);
        }
    }

    /**
     * Retrieves the summary for a specific record and language.
     * If the translation for the summary does not exist, it creates a new one
     * If it exists but it's not up to date, it creates a summary translation
     *
     * @param ServiceBase $api
     * @param array $args
     * @return array
     * @throws SugarApiExceptionNotAuthorized
     * @throws SugarApiExceptionError
     * @throws SugarApiExceptionNotFound
     * @throws SugarApiException
     */
    public function translate(ServiceBase $api, array $args)
    {
        if (!hasGaiLicense()) {
            throw new SugarApiExceptionNotAuthorized(translate('LBL_GAI_NO_LICENSE_ACCESS'));
        }

        if (!ACLController::checkAccess('SummarizationGai', 'view')) {
            throw new SugarApiExceptionNotAuthorized('LBL_GAI_ERROR_NO_ACCESS');
        }

        $this->requireArgs($args, ['module', 'recordId', 'language']);

        $currentLanguage = Helper::getCurrentUserPreferredLanguage();

        $parentId = $args['recordId'];
        $parentModule = $args['module'];
        $language = $args['language'];

        $retrieveSummaryCriteria = $this->getSummaryCriteriaWithParams([
            ['field' => 'status', 'operator' => 'equals', 'value' => GAIConstants::SUMZ_STATUS_COMPLETED],
            ['field' => 'language', 'operator' => 'equals', 'value' => $language],
        ]);

        $translatedSummary = $this->retrieveSummaryBeanByRelateRecord(
            $parentId,
            $parentModule,
            $retrieveSummaryCriteria
        );

        $retrieveSummaryCriteria = $this->getSummaryCriteriaWithParams([
            ['field' => 'status', 'operator' => 'equals', 'value' => GAIConstants::SUMZ_STATUS_COMPLETED],
            ['field' => 'is_translate', 'operator' => 'equals', 'value' => false],
        ]);

        $mainSummary = $this->retrieveSummaryBeanByRelateRecord(
            $parentId,
            $parentModule,
            $retrieveSummaryCriteria
        );

        if (!($mainSummary instanceof SugarBean)) {
            //if main summary is not completed, we cannot translate anything till it is completed
            return [
                'status' => GAIConstants::SUMZ_STATUS_NOT_FOUND,
            ];
        }

        $hash = md5($mainSummary->eval_id);

        if (!$translatedSummary instanceof SugarBean) {
            $translatedSummary = $this->createSummaryBean(
                $parentId,
                $parentModule,
                GAIConstants::SUMZ_STATUS_READY_FOR_INGEST,
                $language,
                true,
                $hash
            );

            try {
                $translate = true;
                $confirmation = $this->confirmIngestDataSent(
                    $translatedSummary,
                    $parentId,
                    $parentModule,
                    $language,
                    $translate
                );

                return $confirmation;
            } catch (Exception $e) {
                return $this->failedConfirmDataSent($translatedSummary, $e, null);
            }
        }

        $translationSummaryHash = $translatedSummary->hash;

        if ($translationSummaryHash !== $hash) {
            $newTranslatedSummary = $this->createSummaryBean(
                $parentId,
                $parentModule,
                GAIConstants::SUMZ_STATUS_READY_FOR_INGEST,
                $language,
                true,
                $hash
            );
            try {
                $translate = true;
                $confirmation = $this->confirmIngestDataSent(
                    $newTranslatedSummary,
                    $parentId,
                    $parentModule,
                    $language,
                    $translate,
                    $translatedSummary
                );

                return $confirmation;
            } catch (Exception $e) {
                return $this->failedConfirmDataSent($newTranslatedSummary, $e, $translatedSummary);
            }
        }

        $lastSyncDate = Helper::formatDateAsIso($translatedSummary, 'last_sync_date');

        $response = [
            'status' =>$translatedSummary->status,
            'currentLanguage' => $language,
            'summaryLanguage' => $translatedSummary->language,
            'summary' => $translatedSummary->summary,
            'lastSyncDate' => $lastSyncDate,
            'neededFollowup' => $translatedSummary->needed_followup,
        ];

        return $response;
    }

    /**
     * Get the usage token of the vendor.
     *
     * @param ServiceBase $api
     * @param array $args
     * @return array
     */
    public function getUsageToken(ServiceBase $api, array $args): array
    {
        if (!hasGaiLicense()) {
            throw new SugarApiExceptionNotAuthorized(translate('LBL_GAI_NO_LICENSE_ACCESS'));
        }

        if (!ACLController::checkAccess('SummarizationGai', 'view')) {
            throw new SugarApiExceptionNotAuthorized('LBL_GAI_ERROR_NO_ACCESS');
        }

        try {
            $tokenUsage = Helper::getTokenUsage();

            return [
                'data' => $tokenUsage,
                'error' => false,
            ];
        } catch (Exception $e) {
            return [
                'data' => null,
                'error' => true,
            ];
        }
    }

    /**
     * Force ingest data for delta, usually used when delta timeout occurs.
     *
     * @param ServiceBase $api
     * @param array $args
     * @return array
     */
    private function forceIngestDataForDelta(ServiceBase $api, array $args):array
    {
        if (!hasGaiLicense()) {
            throw new SugarApiExceptionNotAuthorized(translate('LBL_GAI_NO_LICENSE_ACCESS'));
        }

        $this->requireArgs($args, ['module', 'recordId']);

        global $current_user;

        $parentId = $args['recordId'];
        $parentModule = $args['module'];

        $summaryCriteriaTimeout = $this->retrieveSummaryCriteriaWithStatus(
            GAIConstants::SUMZ_STATUS_PENDING
        );
        $summaryCriteriaReadyForIngest = $this->retrieveSummaryCriteriaWithStatus(
            GAIConstants::SUMZ_STATUS_READY_FOR_INGEST
        );
        $summaryCriteriaCompleted = $this->retrieveSummaryCriteriaWithStatus(
            GAIConstants::SUMZ_STATUS_COMPLETED
        );

        $summaryBeanInPending = $this->retrieveSummaryBeanByRelateRecord(
            $parentId,
            $parentModule,
            $summaryCriteriaTimeout
        );

        $hasTimeoutedBean = ($summaryBeanInPending instanceof SugarBean);
        if (!$hasTimeoutedBean) {
            // forceIngestDataForDelta triggered by timeout, but summary was resolved in the meantime
            // so continue ingestion
            $args['force'] = false;

            return $this->ingest($api, $args);
        }

        $summaryBeanCompleted = $this->retrieveSummaryBeanByRelateRecord(
            $parentId,
            $parentModule,
            $summaryCriteriaCompleted
        );

        $summaryBeanReadyForIngest = $this->retrieveSummaryBeanByRelateRecord(
            $parentId,
            $parentModule,
            $summaryCriteriaReadyForIngest
        );

        if ($summaryBeanReadyForIngest instanceof SugarBean) {
            // Check to prevent race condition if two users hit a timeout simultaneously
            $this->deleteTimeoutBean($summaryBeanInPending);

            return $this->deltaResponseReadyForIngest($summaryBeanCompleted);
        }

        $summaryBean = $this->createSummaryBean(
            $parentId,
            $parentModule,
            GAIConstants::SUMZ_STATUS_READY_FOR_INGEST,
            $summaryBeanCompleted->language
        );

        $this->deleteTimeoutBean($summaryBeanInPending);

        $this->createIngestScheduler($parentId, $parentModule, $current_user->id, false);

        return $this->deltaResponseReadyForIngest($summaryBeanCompleted);
    }
}
