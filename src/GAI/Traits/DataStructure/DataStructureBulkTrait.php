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

namespace Sugarcrm\Sugarcrm\GAI\Traits\DataStructure;

use SugarBean;
use BeanFactory;
use Sugarcrm\Sugarcrm\GAI\Helper;
use Sugarcrm\Sugarcrm\GAI\Adapter\AdapterFactory;
use Sugarcrm\Sugarcrm\GAI\Constants\GAIConstants;
use Sugarcrm\Sugarcrm\GAI\Traits\HelperBulkTrait;
use Sugarcrm\Sugarcrm\GAI\Traits\SummaryTrait;
use Sugarcrm\Sugarcrm\GAI\Traits\Service\DataServiceBulkTrait;
use Sugarcrm\Sugarcrm\GAI\Client\Constants\GAIType;

trait DataStructureBulkTrait
{
    use HelperBulkTrait, SummaryTrait, DataServiceBulkTrait;

    /**
     * Confirm that the data was sent to the GAI Service
     *
     * @param SugarBean $summaryBean
     * @param string $parentId
     * @param string $parentModule
     * @param string $language
     * @param bool $translate
     * @param SugarBean|null $oldSummaryBean
     *
     * @return array
     */
    public function confirmIngestDataSent(
        SugarBean $summaryBean,
        string $parentId,
        string $parentModule,
        string $language,
        bool $translate,
        $oldSummaryBean = null
    ): array {
        $currentLanguage = Helper::getCurrentUserPreferredLanguage();
        $evalId = $this->confirmDataSent($parentId, $parentModule, $language, $translate);

        $summaryBean->status = GAIConstants::SUMZ_STATUS_INGEST_SUCCESS;
        $summaryBean->processed = true;
        $summaryBean->eval_id = $evalId;
        $summaryBean->save();

        $resp = [
            'status' => GAIConstants::SUMZ_STATUS_INGEST_SUCCESS,
            'evalId' => $evalId,
            'currentLanguage' => $currentLanguage,
            'summaryLanguage' => '',
        ];

        if (($oldSummaryBean instanceof SugarBean) && $oldSummaryBean->summary) {
            $lastSyncDate = Helper::formatDateAsIso($oldSummaryBean, 'last_sync_date');

            $resp['summary'] = $oldSummaryBean->summary;
            $resp['lastSyncDate'] = $lastSyncDate;
            $resp['summaryLanguage'] = $oldSummaryBean->language;
            $resp['neededFollowup'] = $oldSummaryBean->needed_followup;
            $resp['engagedContacts'] = $oldSummaryBean->engaged_contacts;
        }

        return $resp;
    }

    /**
     * No changes was detected during the process of sending new data to GAI Service, so we have to notify the UI
     * @param bool $isFirstIngest
     * @param SugarBean $summaryBean
     * @param null|SugarBean $newSummaryBean
     *
     * @return array
     */
    public function noChangesDetected(bool $isFirstIngest, SugarBean $summaryBean, ?SugarBean $newSummaryBean): array
    {
        if ($isFirstIngest) {
            return $this->responseNoChangesDetectedFirstIngest($summaryBean);
        } else {
            return $this->responseNoChangesDetectedDelta($newSummaryBean, $summaryBean);
        }
    }

    /**
     * Processes the delta ingest for the summary.
     *
     * @param string $parentId The ID of the parent record.
     * @param string $parentModule The module name of the parent record.
     * @param array $gaiBulkConfig The configuration for GAI bulk processing.
     * @param SugarBean $summaryBean The current summary bean.
     * @param bool $changesDetected A reference to detect if changes were made.
     * @param SugarBean|null $oldSummary The old summary bean.
     * @param SugarBean|null $newSummaryBean The new summary bean.
     *
     * @return SugarBean|array The newly created summary bean or array with message in case of failure.
     */
    public function processDeltaIngest(
        string $parentId,
        string $parentModule,
        array $gaiBulkConfig,
        SugarBean $summaryBean,
        bool &$changesDetected,
        ?SugarBean &$oldSummary,
        ?SugarBean &$newSummaryBean
    ): SugarBean {
        if ($summaryBean->status !== GAIConstants::SUMZ_STATUS_COMPLETED) {
            $oldSummary = $this->retrieveSummaryBeanByRelateRecord(
                $parentId,
                $parentModule,
                [
                    'whereCriteria' => [
                        'status' => [
                            'value' => GAIConstants::SUMZ_STATUS_COMPLETED,
                            'operator' => 'equals',
                        ],
                        'is_translate' => [
                            'value' => false,
                            'operator' => 'equals',
                        ],
                    ],
                    'orderBy' => [
                        'field' => 'date_modified',
                        'direction' => 'DESC',
                    ],
                    'limit' => 1,
                ],
            );

            if (!$oldSummary) {
                $oldSummary = null;
            }
        }

        $this->validateDeltaState($summaryBean);

        //based on delta readiness state checks here oldSummary is the same as summaryBean
        $oldSummary = $summaryBean;

        $newSummaryBean = $this->createSummaryBean(
            $parentId,
            $parentModule,
            GAIConstants::SUMZ_STATUS_READY_FOR_INGEST,
            $summaryBean->language,
        );

        $newSummaryBean->status = GAIConstants::SUMZ_STATUS_PENDING;
        $newSummaryBean->processed = true;
        $newSummaryBean->save();

        $this->ingestData(
            $parentId,
            $parentModule,
            $parentId,
            $gaiBulkConfig,
            true,
            $changesDetected,
            $summaryBean->last_sync_date,
            null
        );

        return $newSummaryBean;
    }

    /**
     * Discover and send target data to GAI Service
     *
     * @param string $rootRecordId
     * @param string $rootRecordModule
     * @param string $recordId
     * @param array $metadata
     * @param bool $isRoot     indicate if is the main record where the process started ex: Account
     * @param bool $changesDetected  indicate if changes were detected and at least 1 request was sent
     * @param string|null $lastSyncDate
     * @param SugarBean|null $parentBean
     *
     * @return void
     */
    public function ingestData(
        string $rootRecordId,
        string $rootRecordModule,
        string $recordId,
        array $metadata,
        bool $isRoot,
        bool &$changesDetected,
        ?string $lastSyncDate,
        ?SugarBean $parentBean
    ) {
        $fields = [];
        $relatedDataConfig = [];
        $targetModule = '';

        $maxRecordCount = 100;
        $relationship = null;
        $maxDataRange = 1095;

        if (array_key_exists('module', $metadata)) {
            $targetModule = $metadata['module'];
        } else {
            return;
        }

        if (array_key_exists('fields', $metadata) && safeCount($metadata['fields']) > 0) {
            $fields = $metadata['fields'];
        }

        if (array_key_exists('maxDataRange', $metadata)) {
            $maxDataRange = $metadata['maxDataRange'];
        }

        if (array_key_exists('maxRecordCount', $metadata)) {
            $maxRecordCount = $metadata['maxRecordCount'];
        }

        if (array_key_exists('relationship', $metadata)) {
            $relationship = $metadata['relationship'];
        }

        if (array_key_exists('relatedDataConfig', $metadata)) {
            $relatedDataConfig = $metadata['relatedDataConfig'];
        }

        if ($isRoot) {
            $bean = BeanFactory::retrieveBean($targetModule, $recordId);

            if (!($bean instanceof SugarBean)) {
                return;
            }

            $dateModified = $bean->date_modified;

            //check if the record was never synced or the record was modified after the last sync
            if (is_null($lastSyncDate) ||
                ($lastSyncDate && Helper::dateCompare(['date' => $dateModified], ['date' => $lastSyncDate]))
            ) {
                $fieldsList = $this->getFieldsList($bean, $fields);

                $data = $this->createRootCollection($bean, $fieldsList, $targetModule);

                if ($data && !empty($data)) {
                    $adapterData = [
                        'adapterType' => GAIType::GAI_DATA_INGEST,
                        'usecaseType' => GAIType::GAI_SUMMARY,
                        'objectType' => $rootRecordModule,
                        'objectId' => $rootRecordId,
                        'data' => $data,
                    ];

                    $ingestionAdapter = (AdapterFactory::getDataAdapterInstance($adapterData));
                    $payload = $ingestionAdapter->getIngestData();

                    $changesDetected = true;

                    $this->sendDataIngest($payload);
                }

                unset($fieldsList);
                $fieldsList = null;
            }

            foreach ($relatedDataConfig as $relatedModule) {
                $this->ingestData(
                    $rootRecordId,
                    $rootRecordModule,
                    '',
                    $relatedModule,
                    false,
                    $changesDetected,
                    $lastSyncDate,
                    $bean
                );
            }
        } else {
            if (!$relationship || !$parentBean->load_relationship($relationship)) {
                return;
            }

            $targetDate = [];
            $operator = '>';
            $where = ' 1 = 0';

            $relatedModule = $parentBean->$relationship->getRelatedModuleName();

            if (!$relatedModule) {
                $relatedModule = $targetModule;
            }

            if ($lastSyncDate) {
                $lastSyncDate = \TimeDate::getInstance()->fromDb($lastSyncDate)->asDb();

                $targetDate = [
                    'date' => $lastSyncDate,
                    'modify' => '-' . $maxDataRange . ' days',
                ];

                $where = $this->createWhereDateTimeQueryForDelta($relatedModule, $targetDate, $operator);
            } else {
                $targetDate = [
                    'modify' => '-' . $maxDataRange . ' days',
                ];

                $where = $this->createWhereDateTimeQuery($relatedModule, $targetDate, $operator);
            }


            if ($maxRecordCount && is_int($maxRecordCount)) {
                $limit = $maxRecordCount;
            }

            $relatedRecords = [];
            $previousEmailHistoryConfig = [];
            $excludedModulesForEmailHistory = [
                'Cases' => true,
                'Accounts' => true,
                'Opportunities' => true,
            ];
            $isEmailHistoryExclusionRequired = $targetModule === 'Emails' &&
                isset($excludedModulesForEmailHistory[$parentBean->getModuleName()]);

            try {
                if ($isEmailHistoryExclusionRequired) {
                    $previousEmailHistoryConfig = $GLOBALS['sugar_config']['hide_history_contacts_emails'];

                    $GLOBALS['sugar_config']['hide_history_contacts_emails'] = $excludedModulesForEmailHistory;
                }

                $sourceModule = $parentBean->getModuleName();

                if ($relationship === 'archived_emails' && $relatedModule === 'Emails') {
                    if ($sourceModule === 'Accounts') {
                        $where .= $this->getDuplicateEmailCondition($sourceModule);
                    }
                }

                $relatedRecords = $parentBean->$relationship->getBeans(
                    [
                        'where' => $where,
                        'orderby' => 'date_modified DESC',
                        'limit' => $limit,
                    ],
                    [
                        'disable_row_level_security' => true,
                        'use_cache' => false,
                    ]
                );
            } catch (\Exception $e) {
            } finally {
                if ($isEmailHistoryExclusionRequired) {
                    $GLOBALS['sugar_config']['hide_history_contacts_emails'] = $previousEmailHistoryConfig;
                }
            }

            if (safeCount($relatedRecords) < 1) {
                return;
            }

            if ($relationship === 'archived_emails' && $relatedModule === 'Emails') {
                //we have to remove the emails from no-reply addresses
                $relatedRecords = $this->filterRelatedEmailsNoReply($relatedRecords);
            }

            $fieldsList = $this->getFieldsList($targetModule, $fields);

            $relatedRecordsCollection = $this->createRelateCollection(
                $parentBean,
                $relatedRecords,
                $fieldsList,
                $targetModule
            );

            if ($relatedRecordsCollection && !empty($relatedRecordsCollection)) {
                $adapterData = [
                    'adapterType' => GAIType::GAI_DATA_INGEST,
                    'usecaseType' => GAIType::GAI_SUMMARY,
                    'objectType' => $rootRecordModule,
                    'objectId' => $rootRecordId,
                    'data' => $relatedRecordsCollection,
                ];

                $ingestionAdapter = (AdapterFactory::getDataAdapterInstance($adapterData));
                $payload = $ingestionAdapter->getIngestData();

                $changesDetected = true;

                $this->sendDataIngest($payload);
            }

            unset($relatedRecordsCollection);
            unset($fieldsList);
            $relatedRecordsCollection = null;
            $fieldsList = null;

            //iterate again to prevent the children to be sent before the parents
            foreach ($relatedRecords as $relatedRecord) {
                foreach ($relatedDataConfig as $relatedModule) {
                    $this->ingestData(
                        $rootRecordId,
                        $rootRecordModule,
                        '',
                        $relatedModule,
                        false,
                        $changesDetected,
                        $lastSyncDate,
                        $relatedRecord
                    );
                }
            }
        }
    }

    /**
     * Discover fields of a module
     *
     * @param SugarBean $bean
     *
     * @return array
     */
    public function discoverModuleFields(SugarBean $bean): array
    {
        $fieldDefinitions = $bean->getFieldDefinitions();
        $filteredKeys = [];

        foreach ($fieldDefinitions as $key => $def) {
            if (!isset($def['type']) || $def['type'] !== 'link') {
                $filteredKeys[] = $key;
            }
        }

        return $filteredKeys;
    }

    /**
     * Create record data, by populating the targeted fields with the values from the bean
     *
     * @param SugarBean $bean
     * @param array $fields
     * @param array $deniedFields
     *
     * @return array
     */
    public function createRecordData(SugarBean $bean, array $fields, array $deniedFields): array
    {
        $parentObject = [];

        foreach ($fields as $field) {
            if (in_array($field, $deniedFields)) {
                continue;
            }

            if (!empty($bean->{$field})) {
                $fieldValue = $bean->{$field};

                if (is_array($fieldValue)) {
                    continue;
                }

                $parentObject[$field] = "{$fieldValue}";
            }
        }

        return $parentObject;
    }

    /**
     * Create the where clause for the SugarQuery object based on the max data range
     *
     * @param string $targetModule
     * @param array $dateMeta ex: ['date' => '2021-01-01', 'modify' => '+1 day']
     * @param string $operator ex: '>', '<', '>=', '<='
     *
     * we have to be able to run under php 8.1 so we can't use mixed type
     * @return array|string
     */
    public function createWhereDateTimeQuery(string $targetModule, array $dateMeta, string $operator = '>')
    {
        global $db;

        $where = [];
        $modifyDate = null;
        $maxModifiedDateDb = null;
        $date = null;
        $modifyDate = null;

        if (array_key_exists('date', $dateMeta)) {
            $date = $dateMeta['date'];
        }

        if (array_key_exists('modify', $dateMeta)) {
            $modifyDate = $dateMeta['modify'];
        }

        if (!$modifyDate && !$date) {
            return [];
        }

        if (array_key_exists('modify', $dateMeta)) {
            $modifyDate = $dateMeta['modify'];
        }

        if ($modifyDate && !$date) {
            //date is empty - use the current date (now)
            $sugarDateTime = new \SugarDateTime();
            $sugarDateTime->modify($modifyDate);
            $maxModifiedDateDb = $sugarDateTime->asDb();
        } elseif (!$modifyDate && $date) {
            $dateTime = \TimeDate::getInstance();
            // use the input date and set it as maxModifiedDateDb
            $maxModifiedDateDb = $dateTime->fromDb($date)->asDb();
        } elseif ($modifyDate && $date) {
            // use the input date and add/remove days/months from it based on modifyDate
            $dateTime = \TimeDate::getInstance();
            $dateObject = $dateTime->fromDb($date);
            $dateObject->modify($modifyDate);
            $maxModifiedDateDb = $dateTime->asDb($dateObject);
        }

        //we're using date_entered to limit the records since we will fetch only the X records orderd by date_modified
        if ($targetModule === 'Emails') {
            $maxModifiedDateDb = $db->quoted($maxModifiedDateDb);

            $where = " emails.date_entered $operator $maxModifiedDateDb ";
        } else {
            $where = [
                'lhs_field' => 'date_entered',
                'operator' => $operator,
                'rhs_value' => $maxModifiedDateDb,
            ];
        }

        return $where;
    }

    /**
     * Create the where clause for the SugarQuery object based on the max data range
     *
     * @param string $targetModule
     * @param array $dateMeta ex: ['date' => '2021-01-01', 'modify' => '+1 day']
     * @param string $operator ex: '>', '<', '>=', '<='
     *
     * we have to be able to run under php 8.1 so we can't use mixed type
     * @return array|string
     */
    public function createWhereDateTimeQueryForDelta(string $targetModule, array $dateMeta, string $operator = '>')
    {
        global $db;

        $where = [];
        $modifyDate = null;
        $maxModifiedDateDb = null;
        $maxEnteredDateDb = null;
        $date = null;
        $modifyDate = null;

        if (array_key_exists('date', $dateMeta) && $dateMeta['date']) {
            $date = $dateMeta['date'];
        }

        if (array_key_exists('modify', $dateMeta) && $dateMeta['modify']) {
            $modifyDate = $dateMeta['modify'];
        }

        if (!$modifyDate || !$date) {
            $where = " 1 = 0 ";

            return $where;
        }

        // date_entered > now - modify date
        $sugarDateTime = new \SugarDateTime();
        $sugarDateTime->modify($modifyDate);
        $maxEnteredDateDb = $sugarDateTime->asDb();

        // date_modified > date (last_sync_date)
        $dateTime = \TimeDate::getInstance();
        $dateObject = $dateTime->fromDb($date);

        $maxModifiedDateDb = $dateTime->asDb($dateObject);

        $relatedModuleBean = BeanFactory::newBean($targetModule);

        if (!($relatedModuleBean instanceof SugarBean)) {
            $where = " 1 = 0 ";

            return $where;
        }

        $tableTableName = $relatedModuleBean->getTableName();

        $maxEnteredDateDb = $db->quoted($maxEnteredDateDb);
        $maxModifiedDateDb = $db->quoted($maxModifiedDateDb);

        if ($targetModule === 'Emails') {
            $where = " emails.date_entered $operator $maxEnteredDateDb AND" .
                " emails.date_modified $operator $maxModifiedDateDb";
        } else {
            $where =  " $tableTableName.date_entered $operator $maxEnteredDateDb AND" .
                " $tableTableName.date_modified $operator $maxModifiedDateDb";
        }

        return $where;
    }

    /**
     * Discover fields of a module based on the inconming fields list
     *
     * @param string|SugarBean $targetModule
     * @param array $fields
     *
     * @return array
     */
    public function getFieldsList($targetModule, array $fields): array
    {
        $fieldsList = [];

        if (safeCount($fields) < 1) {
            $moduleBean = null;

            if (!($targetModule instanceof SugarBean) && is_string($targetModule)) {
                $moduleBean = BeanFactory::newBean($targetModule);
            } elseif ($targetModule instanceof SugarBean) {
                $moduleBean = $targetModule;
            } else {
                return $fieldsList;
            }

            $fieldsList = $this->discoverModuleFields($moduleBean);
        } else {
            $fieldsList = $fields;
        }

        return $fieldsList;
    }

    /**
     * Populate the collection that will be used to create the JSON data
     *
     * @param array $collection
     * @param array &$fieldsList -> pass by reference
     * @param SugarBean $record
     * @param string $targetModule
     * @param string|null $parentModule -> will be null if the record is the root record, it has no parent
     * @param string|null $parentId -> will be null if the record is the root record, it has no parent
     *
     * @return bool
     */
    public function populateCollection(
        array &$collection,
        array $fieldsList,
        SugarBean $record,
        string $targetModule,
        ?string $parentModule,
        ?string $parentId
    ): bool {
        $deniedFields = $this->getDeniedFields($targetModule);

        $targetBeanValues = $this->createRecordData($record, $fieldsList, $deniedFields);
        $targetBeanValuesSize = strlen(json_encode($targetBeanValues));

        if ($targetBeanValuesSize > GAIConstants::DATA_MAX_SIZE) {
            unset($targetBeanValues, $targetBeanValuesSize);
            return true;
        }

        $currentCollectionSize = strlen(json_encode($collection));

        if (($currentCollectionSize + $targetBeanValuesSize) > GAIConstants::DATA_MAX_SIZE) {
            unset($targetBeanValues, $targetBeanValuesSize, $currentCollectionSize);
            return false;
        }

        $collection[] = [
            'date' => $record->date_modified,
            'moduleName' => $targetModule,
            'objectId' => $record->id,
            'parentObjectType' => $parentModule,
            'parentObjectId' => $parentId,
            'objectData' => $targetBeanValues,
        ];

        unset($targetBeanValues);
        $targetBeanValues = null;

        return true;
    }

    /**
     * Get the denied fields for a specific module
     *
     * @param string $module
     * @return array
     */
    public function getDeniedFields(string $module): array
    {
        $deniedFields = [];

        if ($module === 'Emails') {
            $deniedFields[] = 'description_html';
        }

        if ($module === 'Cases') {
            $deniedFields[] = 'case_ai_sentiment';
            $deniedFields[] = 'case_ai_date_generated';
        }

        return $deniedFields;
    }

    /**
     * Create the collection for the record where the entire logic will start
     *
     * @param SugarBean $rootBean
     * @param array $fieldsList
     * @param string $targetModule
     *
     * @return array
     */
    public function createRootCollection(SugarBean $rootBean, array $fieldsList, string $targetModule): array
    {
        $data = [];

        $this->populateCollection(
            $data,
            $fieldsList,
            $rootBean,
            $targetModule,
            null,
            null
        );

        return $data;
    }

    /**
     * Create the collection for the related records of the root record
     *
     * @param SugarBean $parentBean
     * @param array $relatedRecords
     * @param array $fieldsList
     * @param string $targetModule
     *
     * @return array
     */
    public function createRelateCollection(
        SugarBean $parentBean,
        array $relatedRecords,
        array $fieldsList,
        string $targetModule
    ): array {
        $relatedRecordsCollection = [];

        $parentModule = BeanFactory::getModuleName($parentBean);
        $parentId = $parentBean->id;

        foreach ($relatedRecords as $relatedRecord) {
            $continue = $this->populateCollection(
                $relatedRecordsCollection,
                $fieldsList,
                $relatedRecord,
                $targetModule,
                $parentModule,
                $parentId
            );

            if ($continue === false) {
                break;
            }
        }

        return $relatedRecordsCollection;
    }

    /**
     * Filter related emails to exclude no-reply addresses
     *
     * @param array $relatedRecords
     *
     * @return array Filtered related records
     */
    public function filterRelatedEmailsNoReply($relatedRecords)
    {
        $filtered = [];

        foreach ($relatedRecords as $recordId => $relatedRecord) {
            if (isset($relatedRecord->from_addr) && !empty($relatedRecord->from_addr)) {
                $emailAddress = strtolower($relatedRecord->from_addr);

                if (strpos($emailAddress, 'reply') === false) {
                    $filtered[$recordId] = $relatedRecord;
                    continue;
                }

                // Check if the email address is a no-reply address
                if (strpos($emailAddress, 'no-reply') !== false ||
                    strpos($emailAddress, 'noreply') !== false ||
                    strpos($emailAddress, 'donotreply') !== false ||
                    strpos($emailAddress, 'no_reply') !== false ||
                    strpos($emailAddress, 'do-not-reply') !== false
                ) {
                    continue;
                }
            }

            $filtered[$recordId] = $relatedRecord;
        }

        return $filtered;
    }
}
