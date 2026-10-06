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

namespace Sugarcrm\Sugarcrm\GAI\Traits;

use SugarBean;
use SugarQuery;
use BeanFactory;
use Exception;
use Sugarcrm\Sugarcrm\GAI\Constants\GAIConstants;
use Sugarcrm\Sugarcrm\GAI\Helper;
use Sugarcrm\Sugarcrm\GAI\Traits\NotificationTrait;
use Sugarcrm\Sugarcrm\GAI\Traits\Service\DataServiceTrait;
use Sugarcrm\Sugarcrm\GAI\Exception\IngestData\SummaryBeanIngestSuccessException;
use Sugarcrm\Sugarcrm\GAI\Exception\IngestData\SummaryBeanInPendingException;
use Sugarcrm\Sugarcrm\GAI\Exception\IngestData\SummaryBeanInProgressException;
use Sugarcrm\Sugarcrm\GAI\Exception\IngestData\SummaryBeanOnHoldException;
use Sugarcrm\Sugarcrm\GAI\Exception\IngestData\SummaryBeanReadyForIngestException;
use Sugarcrm\Sugarcrm\GAI\Exception\BackendNotConfiguredException;

trait HelperBulkTrait
{
    use NotificationTrait, DataServiceTrait;
    /**
     * Check if summary bean is in on hold
     * To determine if the summary is on hold we have to check if there was enough time passed since the last sync
     *
     * @param SugarBean $summaryBean
     *
     * @return array|bool
     */
    public function isOnHold(SugarBean $summaryBean)
    {
        $defaultRefreshRate = '+1 hour'; //TODO this could later be received from the config

        $lastSyncDate = $summaryBean->last_sync_date ? $summaryBean->last_sync_date : $summaryBean->date_modified;

        $lastSyncDataComp = ['date'=> $lastSyncDate, 'modify' => $defaultRefreshRate];

        $nowData = ['date'=> \TimeDate::getInstance()->nowDb()];

        $shouldStartIngestProcess = Helper::dateCompare($nowData, $lastSyncDataComp);

        return !$shouldStartIngestProcess;
    }

    /**
     * Handle the case when there was a failure during delta ingest summary process
     *
     * @param SugarBean|null $summaryBean
     * @param Exception $e
     * @param SugarBean|bool $oldSummary
     *
     * @return array
     */
    public function failedToIngestDelta($summaryBean, \Exception $e, $oldSummary): array
    {
        if ($summaryBean instanceof SugarBean) {
            // something happened we have to reset the status from processing to initial status
            // because we will try again next time when the user goes on the dashlet
            $summaryBean->deleted = 1;
            $summaryBean->error_message = json_encode(['errorMessage' => $e->getMessage()]);
            $summaryBean->status = GAIConstants::SUMZ_STATUS_ERROR;
            $summaryBean->processed = true;
            $summaryBean->save();

            $parentId = $summaryBean->parent_id;
            $moduleId = $summaryBean->parent_type;

            $GLOBALS['log']->error("GAI[IngestSummary][Update][$moduleId][$parentId]: " . $e->getMessage());
        } else {
            $GLOBALS['log']->error('GAI[IngestSummary][Update]: ' . $e->getMessage());
        }

        $statusCode = Helper::getStatusCodeFromError($e);

        $currentLanguage = Helper::getCurrentUserPreferredLanguage();
        $response = [
            'status' => GAIConstants::SUMZ_STATUS_FAILED,
            'statusCode' => $statusCode,
            'errorMessage' => $e->getMessage(),
            'currentLanguage' => $currentLanguage,
            'summaryLanguage' => '',
        ];


        if ($oldSummary instanceof SugarBean) {
            $lastSyncDate = Helper::formatDateAsIso($oldSummary, 'last_sync_date');

            $response['summary'] = $oldSummary->summary;
            $response['lastSyncDate'] = $lastSyncDate;
            $response['summaryLanguage'] = $oldSummary->language;
            $response['neededFollowup'] = $oldSummary->needed_followup;
            $response['engagedContacts'] = $oldSummary->engaged_contacts;
        }

        return $response;
    }

    /**
     * Handle the case when there was a failure during inital ingest summary process
     *
     * @param SugarBean $summaryBean
     * @param Exception $e
     *
     * @return array
     */
    public function failedToIngestInitial(SugarBean $summaryBean, \Exception $e): array
    {
        if ($summaryBean instanceof SugarBean) {
            $summaryBean->status = GAIConstants::SUMZ_STATUS_ERROR;
            $summaryBean->error_message = json_encode(['errorMessage' => $e->getMessage()]);
            $summaryBean->deleted = 1;
            $summaryBean->save();

            $parentId = $summaryBean->parent_id;
            $moduleId = $summaryBean->parent_type;

            $GLOBALS['log']->error("GAI[IngestSummary][Initial][$moduleId][$parentId]: " . $e->getMessage());
        } else {
            $GLOBALS['log']->error('GAI[IngestSummary][Initial]: ' . $e->getMessage());
        }

        $statusCode = Helper::getStatusCodeFromError($e);
        $currentLanguage = Helper::getCurrentUserPreferredLanguage();

        return [
            'status' => GAIConstants::SUMZ_STATUS_FAILED,
            'statusCode' => $statusCode,
            'errorMessage' => $e->getMessage(),
            'currentLanguage' => $currentLanguage,
            'summaryLanguage' => '',
        ];
    }

    /**
     * Handle the case when there was a failure during confirm data sent process
     *
     * @param SugarBean $summaryBean
     * @param Exception $e
     * @param mixed $oldSummary
     * @return array{status: 'failed', errorMessage: string, summary: mixed, last_sync_date: string}
     */
    public function failedConfirmDataSent(SugarBean $summaryBean, \Exception $e, $oldSummary)
    {
        //for whatever reason the config was invalid and we didn't get any data
        //so we have to delete the initial summary bean because it's a stuck bean
        $summaryBean->deleted = 1;
        $summaryBean->status = GAIConstants::SUMZ_STATUS_ERROR;
        $summaryBean->error_message = json_encode(['errorMessage' => $e->getMessage()]);
        $summaryBean->save();

        $parentId = $summaryBean->parent_id;
        $moduleId = $summaryBean->parent_type;

        $GLOBALS['log']->error("GAI[IngestSummary][Confirm][$moduleId][$parentId]: " . $e->getMessage());

        $currentLanguage = Helper::getCurrentUserPreferredLanguage();
        $resp = [
            'status' => GAIConstants::SUMZ_STATUS_FAILED,
            'errorMessage' => $e->getMessage(),
            'currentLanguage' => $currentLanguage,
            'summaryLanguage' => '',
        ];

        if ($oldSummary instanceof SugarBean) {
            $lastSyncDate = Helper::formatDateAsIso($oldSummary, 'last_sync_date');

            $resp['summary'] = $oldSummary->summary;
            $resp['lastSyncDate'] = $lastSyncDate;
            $resp['summaryLanguage'] = $oldSummary->language;
            $resp['neededFollowup'] = $oldSummary->needed_followup;
            $resp['engagedContacts'] = $oldSummary->engaged_contacts;
        }

        return $resp;
    }

    /**
     * Handle the case when there was no changed detected on the first ingest summary process
     * This case can be achived normally only if there is something wrong with the config
     *
     * @param SugarBean $summaryBean
     * @return void
     */
    public function responseNoChangesDetectedFirstIngest(SugarBean $summaryBean)
    {
        $errorMessage = 'GAI[IngestSummary-FirstIngest]: possible invalid config from GAI Service';

        //for whatever reason the config was invalid and we didn't get any data
        //so we have to delete the initial summary bean because it's a stuck bean
        $summaryBean->deleted = 1;
        $summaryBean->status = GAIConstants::SUMZ_STATUS_ERROR;
        $summaryBean->error_message = json_encode(['errorMessage' => $errorMessage]);
        //todo add  $summaryBean->error_message once we will have it from backend
        $summaryBean->save();

        $GLOBALS['log']->error($errorMessage);

        //the only case where would be that the backend is not configured properly
        //since it's the initiall ingest we have to have data to sent
        throw new BackendNotConfiguredException();
    }

    public function responseNoChangesDetectedDelta(SugarBean $newSummaryBean, SugarBean $summary)
    {
        //no changes detected so we have nothing new to do a summary on
        //so we have also to update the summary bean to be completed
        $newSummaryBean->deleted = 1;
        $newSummaryBean->last_sync_date = \TimeDate::getInstance()->nowDb();
        $newSummaryBean->status = GAIConstants::SUMZ_STATUS_ERROR;
        $newSummaryBean->processed = true;
        $newSummaryBean->save();
        //de don't need anymore the new summary bean since there was no changes
        $newSummaryBean->hardDelete();

        $summary->last_sync_date = \TimeDate::getInstance()->nowDb();
        $summary->save();

        $GLOBALS['log']->warning("GAI[IngestSummary-Delta][$summary->id]: no changes detected");
        //no changes detected so we have nothing new to do a summary on

        $lastSyncDate = Helper::formatDateAsIso($summary, 'last_sync_date');
        $currentLanguage = Helper::getCurrentUserPreferredLanguage();

        return [
            'summary' => $summary->summary,
            'lastSyncDate' => $lastSyncDate,
            'status' => GAIConstants::SUMZ_STATUS_COMPLETED, //nothing new to summarize
            'currentLanguage' => $currentLanguage,
            'summaryLanguage' => $summary->language,
            'neededFollowup' => $summary->needed_followup,
            'engagedContacts' => $summary->engaged_contacts,
        ];
    }

    /**
     * Create response for delta response when current summary is in pending
     *
     * @param SugarBean|bool $oldSummary
     * @return array
     */
    public function deltaResponsePending($oldSummary): array
    {
        $currentLanguage = Helper::getCurrentUserPreferredLanguage();
        $response = [
            'status' => GAIConstants::SUMZ_STATUS_PENDING,
            'currentLanguage' => $currentLanguage,
            'summaryLanguage' => '',
        ];

        if ($oldSummary instanceof SugarBean) {
            $lastSyncDate = Helper::formatDateAsIso($oldSummary, 'last_sync_date');

            $response['summary'] = $oldSummary->summary;
            $response['lastSyncDate'] = $lastSyncDate;
            $response['summaryLanguage'] = $oldSummary->language;
            $response['neededFollowup'] = $oldSummary->needed_followup;
            $response['engagedContacts'] = $oldSummary->engaged_contacts;
        }

        return $response;
    }

    /**
     * Create response for delta response when current summary is on hold status
     *
     * @param SugarBean $summaryBean
     * @return array
     */
    public function deltaResponseOnHold(SugarBean $summaryBean): array
    {
        //not enough time has passed from the last sync date to start the ingest process
        //todo check here to see what we have to do in UI when
        $lastSyncDate = Helper::formatDateAsIso($summaryBean, 'last_sync_date');
        $currentLanguage = Helper::getCurrentUserPreferredLanguage();

        return [
            'status' => GAIConstants::SUMZ_STATUS_ON_HOLD,
            'summary' => $summaryBean->summary,
            'lastSyncDate' => $lastSyncDate,
            'currentLanguage' => $currentLanguage,
            'summaryLanguage' => $summaryBean->language,
            'neededFollowup' => $summaryBean->needed_followup,
            'engagedContacts' => $summaryBean->engaged_contacts,
        ];
    }

    /**
     * Create response for delta response when current summary is in progress
     *
     * @param SugarBean $summaryBean
     * @param string $status
     * @param SugarBean|bool $oldSummary
     * @return array
     */
    public function deltaResponseInProgress(SugarBean $summaryBean, string $status, $oldSummary): array
    {
        $currentLanguage = Helper::getCurrentUserPreferredLanguage();
        $resp = [
            'status' => $status,
            'evalId' => $summaryBean->eval_id,
            'currentLanguage' => $currentLanguage,
            'summaryLanguage' => '',
        ];

        if ($oldSummary instanceof SugarBean) {
            $lastSyncDate = Helper::formatDateAsIso($oldSummary, 'last_sync_date');

            $resp['summary'] = $oldSummary->summary;
            $resp['lastSyncDate'] = $lastSyncDate;
            $resp['summaryLanguage'] = $oldSummary->language;
            $resp['neededFollowup'] = $oldSummary->needed_followup;
            $resp['engagedContacts'] = $oldSummary->engaged_contacts;
        }

        return $resp;
    }

    /**
     * The summary is queued to be handled by a scheduled job
     *
     * @param SugarBean|null|false $oldSummary
     *
     * @return array
     */
    public function deltaResponseReadyForIngest($oldSummary): array
    {
        $currentLanguage = Helper::getCurrentUserPreferredLanguage();

        $resp = [
            'status' => GAIConstants::SUMZ_STATUS_READY_FOR_INGEST,
            'currentLanguage' => $currentLanguage,
            'summaryLanguage' => '',
        ];

        if ($oldSummary instanceof SugarBean) {
            $lastSyncDate = Helper::formatDateAsIso($oldSummary, 'last_sync_date');

            $resp['summary'] = $oldSummary->summary;
            $resp['lastSyncDate'] = $lastSyncDate;
            $resp['summaryLanguage'] = $oldSummary->language;
            $resp['neededFollowup'] = $oldSummary->needed_followup;
            $resp['engagedContacts'] = $oldSummary->engaged_contacts;
        }

        return $resp;
    }

    /**
     * Handles various custom exceptions related to the summary ingest process.
     *
     * @param Exception $e The caught exception.
     * @param SugarBean $summaryBean The current summary bean.
     * @param SugarBean|null $oldSummary The previous summary bean, if any.
     * @param SugarBean|null $newSummaryBean The new summary bean, if created.
     * @return array The appropriate response for the exception.
     */
    protected function handleIngestState(
        Exception $e,
        SugarBean $summaryBean,
        ?SugarBean $oldSummary,
        ?SugarBean $newSummaryBean
    ): array {
        if ($e instanceof SummaryBeanReadyForIngestException) {
            $this->notifyUserForFirstIngets($summaryBean, $oldSummary, GAIConstants::SUMZ_STATUS_READY_FOR_INGEST);
            // this means a scheduler job is already create for this summmary
            // so we will have to wait
            // to here will be implemented the logic for GAI-128
            return $this->deltaResponseReadyForIngest($oldSummary);
        } elseif ($e instanceof SummaryBeanInPendingException) {
            // a summary is already in progress

            $this->notifyUserForFirstIngets($summaryBean, $oldSummary, GAIConstants::SUMZ_STATUS_PENDING);
            return $this->deltaResponsePending($oldSummary);
        } elseif ($e instanceof SummaryBeanIngestSuccessException) {
            // the ingest process was completed successfully,

            $this->notifyUserForFirstIngets($summaryBean, $oldSummary, GAIConstants::SUMZ_STATUS_INGEST_SUCCESS);
            // we will return the evalId here for the next step of retrieving the summary
            return $this->deltaResponseInProgress(
                $summaryBean,
                GAIConstants::SUMZ_STATUS_INGEST_SUCCESS,
                $oldSummary
            );
        } elseif ($e instanceof SummaryBeanInProgressException) {
            return $this->deltaResponseInProgress(
                $summaryBean,
                GAIConstants::SUMZ_STATUS_PROCESSING,
                $oldSummary
            );
        } elseif ($e instanceof SummaryBeanOnHoldException) {
            // not enough time has passed to start a new summary
            return $this->deltaResponseOnHold($summaryBean);
        } else {
            // Default handling for any other exception, including unknown ones
            return $this->failedToIngestDelta($newSummaryBean, $e, $summaryBean);
        }
    }

    /**
     * Create a notification for the user to inform that summary is ready
     * @param SugarBean $summaryBean
     * @return void
     */
    public function notifyUserForFirstIngets(SugarBean $summaryBean, ?SugarBean $oldSummaryBean, string $status): void
    {
        global $current_user;

        if (($current_user && $current_user->id && !($summaryBean->last_sync_date))
            && !($oldSummaryBean instanceof SugarBean)
        ) {
            if ($status === GAIConstants::SUMZ_STATUS_INGEST_SUCCESS && is_string($summaryBean->eval_id)) {
                try {
                    if ($this->isSummaryReadyOnGaiService($summaryBean->eval_id) === true) {
                        return;
                    }
                } catch (\Throwable $error) {
                    $GLOBALS['log']->warning("GAI[Notify][User][Check][Summary][State]:" . $error->getMessage());

                    return;
                }
            }

            $this->queueForNotifyUser($summaryBean->id, $current_user->id);
        }
    }

    /**
     * It has to pass all the checks to be able to start the delta process
     *
     * @param SugarBean $summaryBean
     * @return bool
     *
     * @throws SummaryBeanReadyForIngestException
     * @throws SummaryBeanInPendingException
     * @throws SummaryBeanIngestSuccessException
     * @throws SummaryBeanInProgressException
     * @throws SummaryBeanOnHoldException
     */
    public function validateDeltaState(SugarBean $summaryBean): bool
    {
        $isReadyForIngest = $this->isSummaryBeanReadyForIngest($summaryBean);

        if ($isReadyForIngest) {
            throw new SummaryBeanReadyForIngestException();
        }

        $isSummaryInPending = $this->isSummaryBeanInPending($summaryBean);

        if ($isSummaryInPending) {
            throw new SummaryBeanInPendingException();
        }

        $isSummaryIngestSuccess = $this->isSummaryBeanIngestSuccess($summaryBean);

        if ($isSummaryIngestSuccess) {
            throw new SummaryBeanIngestSuccessException();
        }

        $isSummaryInProgress = $this->isSummaryBeanInProgress($summaryBean);

        if ($isSummaryInProgress) {
            throw new SummaryBeanInProgressException();
        }

        $isSummaryOnHold = $this->isOnHold($summaryBean);

        if ($isSummaryOnHold) {
            throw new SummaryBeanOnHoldException();
        }

        return true;
    }

    /**
     * It has to pass all the checks to be able to start the delta process
     *
     * @param SugarBean $summaryBean
     * @param mixed $oldSummary
     *
     * @return array|bool
     */
    public function getDeltaReadinessState(SugarBean $summaryBean, $oldSummary): array|bool
    {
        $isReadyForIngest = $this->isSummaryBeanReadyForIngest($summaryBean);

        if ($isReadyForIngest) {
            // this means a scheduler job is already create for this summmary
            // so we will have to wait
            return $this->deltaResponseReadyForIngest($oldSummary);
        }

        $isSummaryInPending = $this->isSummaryBeanInPending($summaryBean);

        if ($isSummaryInPending) {
            // a summary is already in progress
            return $this->deltaResponsePending($oldSummary);
        }

        $isSummaryIngestSuccess = $this->isSummaryBeanIngestSuccess($summaryBean);

        if ($isSummaryIngestSuccess) {
            // the ingest process was completed successfully,
            // we will return the evalId here for the next step of retrieving the summary
            return $this->deltaResponseInProgress(
                $summaryBean,
                GAIConstants::SUMZ_STATUS_INGEST_SUCCESS,
                $oldSummary
            );
        }

        $isSummaryInProgress = $this->isSummaryBeanInProgress($summaryBean);

        if ($isSummaryInProgress) {
            // the ingest process was completed successfully,
            // we will return the evalId here for the next step of retrieving the summary
            return $this->deltaResponseInProgress(
                $summaryBean,
                GAIConstants::SUMZ_STATUS_PROCESSING,
                $oldSummary
            );
        }

        $isSummaryOnHold = $this->isOnHold($summaryBean);

        if ($isSummaryOnHold) {
            // not enough time has passed to start a new summary
            return $this->deltaResponseOnHold($summaryBean);
        }

        return true;
    }

    /**
     * Check if the given summary bean is Ready for ingest status
     *
     * @param SugarBean $summary
     * @return bool
     */
    public function isSummaryBeanReadyForIngest(SugarBean $summary): bool
    {
        return $this->checkSummaryBeanStatus($summary, GAIConstants::SUMZ_STATUS_READY_FOR_INGEST);
    }

    /**
     * Check if the given summary bean is in pending status
     *
     * @param SugarBean $summary
     * @return bool
     */
    public function isSummaryBeanInPending(SugarBean $summary): bool
    {
        return $this->checkSummaryBeanStatus($summary, GAIConstants::SUMZ_STATUS_PENDING);
    }

    /**
     * Check if the given summary bean is in progress status
     *
     * @param SugarBean $summary
     * @return bool
     */
    public function isSummaryBeanInProgress(SugarBean $summary): bool
    {
        return $this->checkSummaryBeanStatus($summary, GAIConstants::SUMZ_STATUS_PROCESSING);
    }

    /**
     * Check if the given summary bean is in ingest success status
     *
     * @param SugarBean $summary
     * @return bool
     */
    public function isSummaryBeanIngestSuccess(SugarBean $summary): bool
    {
        return $this->checkSummaryBeanStatus($summary, GAIConstants::SUMZ_STATUS_INGEST_SUCCESS);
    }

    /**
     * Check if the given summary bean is in completed status
     *
     * @param SugarBean $summary
     * @return bool
     */
    public function isSummaryBeanCompleted(SugarBean $summary): bool
    {
        return $this->checkSummaryBeanStatus($summary, GAIConstants::SUMZ_STATUS_COMPLETED);
    }

    /**
     * Check if the given summary bean is X status
     *
     * @param SugarBean $summary
     * @param string $status
     *
     * @return bool
     */
    private function checkSummaryBeanStatus(SugarBean $summary, string $status): bool
    {
        return $summary->status === $status;
    }

    /**
     * Retrieves the criteria for summary bean retrieval.
     *
     * @return array The criteria array for retrieving summary beans.
     */
    public function getSummaryCriteria(): array
    {
        return [
            'orderBy' => [
                'field' => 'date_modified',
                'direction' => 'DESC',
            ],
            'limit' => 1,
        ];
    }

    /**
     * Retrieve the criteria for summary bean retrieval with additional parameters.
     *
     * @param array $conditions List of conditions in the format:
     *                          [
     *                              ['field' => 'status', 'operator' => 'equals', 'value' => 'GAIConstants::SUMZ_STATUS_COMPLETED'],
     *                              ['field' => 'language', 'operator' => 'equals', 'value' => 'en_us']
     *                          ]
     * @return array The updated criteria array for retrieving summary beans.
     */
    public function getSummaryCriteriaWithParams(array $conditions): array
    {
        $retrieveSummaryCriteria = $this->getSummaryCriteria();
        $whereCriteria = [];

        foreach ($conditions as $condition) {
            if (isset($condition['field'], $condition['operator'], $condition['value'])) {
                $whereCriteria[$condition['field']] = [
                    'value' => $condition['value'],
                    'operator' => $condition['operator'],
                ];
            }
        }

        $retrieveSummaryCriteria['whereCriteria'] = $whereCriteria;

        return $retrieveSummaryCriteria;
    }
    /**
    * Delete the timeout summary bean
    *
    * @param SugarBean $summary
    */
    public function deleteTimeoutBean(SugarBean $summary)
    {
        $summary->deleted = 1;
        $summary->processed = true;
        $summary->status = GAIConstants::SUMZ_STATUS_TIMEOUT;
        $summary->save();
    }

    /**
     * Get the summary criteria with status
     *
     * @param string $status
     * @return array
     */
    public function retrieveSummaryCriteriaWithStatus(string $status)
    {
        $retrieveSummaryCriteria = $this->getSummaryCriteria();
        $retrieveSummaryCriteria['whereCriteria'] = [
            'status' => [
                'value' => $status,
                'operator' => 'equals',
            ],
            'is_translate' => [
                'value' => false,
                'operator' => 'equals',
            ],
        ];

        return $retrieveSummaryCriteria;
    }
}
