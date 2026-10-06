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
use SugarApiException;
use SugarApiExceptionNotFound;
use Sugarcrm\Sugarcrm\GAI\Helper;
use Sugarcrm\Sugarcrm\GAI\Constants\GAIConstants;

trait SummaryTrait
{
    /**
     * Retrieve summary bean from db by related id and module
     *
     * @param string $relatedRecordId
     * @param string $relatedRecordModule
     * @param array $retrieveCriteria - criteria to retrieve the summary bean
     * example:
     *  [
     *      'whereCriteria' => [
     *      'field' => [value => 'value', operator => 'equals']],
     *      'orderBy' => ['field' => 'field', 'direction' => 'ASC']
     *      'limit' => 1
     *  ]
     * @param bool $ignoreTeamSecurity
     *
     * @return SugarBean|false
     */
    public function retrieveSummaryBeanByRelateRecord(
        string $relatedRecordId,
        string $relatedRecordModule,
        array $retrieveCriteria = [],
        bool $ignoreTeamSecurity = false
    ) {
        $bean = BeanFactory::newBean(GAIConstants::GAI_MODULE_NAME);
        $query = new SugarQuery();

        $whereCriteria = array_key_exists('whereCriteria', $retrieveCriteria) ? $retrieveCriteria['whereCriteria'] : [];
        $orderBy = array_key_exists('orderBy', $retrieveCriteria) ? $retrieveCriteria['orderBy'] : [];
        $limit = array_key_exists('limit', $retrieveCriteria) ? $retrieveCriteria['limit'] : false;

        $ignoreTeamSecurity ? $query->from($bean, ['team_security' => false]) : $query->from($bean);

        $query->select('id');

        if ($whereCriteria && safeCount($whereCriteria) > 0) {
            foreach ($whereCriteria as $field => $value) {
                $targetValue = $value['value'];
                $operator = $value['operator'];

                if ($operator === 'in') {
                    $query->where()->in($field, $targetValue);
                } elseif ($operator === 'notIn') {
                    $query->where()->notIn($field, $targetValue);
                } elseif ($operator === 'notEquals') {
                    $query->where()->notEquals($field, $targetValue);
                } else {
                    $query->where()->equals($field, $targetValue);
                }
            }
        }

        $query->where()->equals('parent_id', $relatedRecordId);
        $query->where()->equals('parent_module', $relatedRecordModule);
        $query->where()->equals('deleted', 0);

        if ($limit !== false) {
            $query->limit($limit);
        }

        if ($orderBy && safeCount($orderBy) > 0 &&
         array_key_exists('field', $orderBy) && array_key_exists('direction', $orderBy)) {
            $query->orderBy($orderBy['field'], $orderBy['direction']);
        }

        $result = $query->execute();

        if (!$result || safeCount($result) < 1) {
            return false;
        }

        $summaryId = $result[0]['id'] ;

        $summaryBean = BeanFactory::retrieveBean(GAIConstants::GAI_MODULE_NAME, $summaryId);

        if (!($summaryBean instanceof SugarBean)) {
            return false;
        }

        return $summaryBean;
    }

    /**
     * Create a new summary bean
     *
     * @param array $fields - fields to set on the summary bean key-field name, value-field value
     * @param bool $returnBeanId - return bean id or bean object
     * @param string $module - module name
     *
     * @return string|SugarBean - bean id or bean object
     */
    public function createBean(array $fields, bool $returnBeanId, $module = GAIConstants::GAI_MODULE_NAME)
    {
        $bean = BeanFactory::newBean($module);

        foreach ($fields as $field => $value) {
            $bean->$field = $value;
        }

        $beanId = $bean->save();

        if ($returnBeanId) {
            return $beanId;
        }

        return $bean;
    }

    /**
     * Create a new summarization record
     *
     * @string $parentId
     * @string $parentModule
     * @string $hash
     * @string $evalId
     * @bool $isTranslate
     *
     * @return void
     */
    public function createSummarizationBean(
        string $parentId,
        string $parentModule,
        string $hash,
        string $evalId,
        bool $isTranslate
    ) {
        $fields = [
            'hash' => $hash,
            'parent_id' => $parentId,
            'parent_module' => $parentModule,
            'status' => GAIConstants::SUMZ_STATUS_PROCESSING,
            'eval_id' => $evalId,
            'language' => Helper::getCurrentUserPreferredLanguage(),
            'is_translate' => $isTranslate,
        ];

        $bean = $this->createBean($fields, false);

        if ($parentModule === 'Opportunities' && $bean->load_relationship('opportunities_summz_gai')) {
            $bean->opportunities_summz_gai->add($parentId);
        }

        if ($parentModule === 'Cases' && $bean->load_relationship('cases_summz_gai')) {
            $bean->cases_summz_gai->add($parentId);
        }

        if ($parentModule === 'Accounts' && $bean->load_relationship('accounts_summz_gai')) {
            $bean->accounts_summz_gai->add($parentId);
        }
    }

    /**
     * Create a new summary bean
     *
     * @param string $parentId
     * @param string $parentModule
     * @param string $status
     *
     * @return SugarBean
     */
    public function createSummaryBean(
        string $parentId,
        string $parentModule,
        string $status,
        string $language = 'en_us',
        bool $isTranslate = false,
        string $hash = ''
    ) {
        $fields = [
            'parent_id' => $parentId,
            'parent_module' => $parentModule,
            'status' => $status,
            'language' => $language,
            'is_translate' => $isTranslate,
        ];

        if ($hash) {
            $fields['hash'] = $hash;
        }

        return $this->createBean($fields, false);
    }

    /**
     * Check if there is a summary in progress for the given parent record
     *
     * @param string $parentId
     * @param string $parentModule
     *
     * @return bool
     */
    public function isSummaryInProgress(string $parentId, string $parentModule)
    {
        $summaryBean = $this->retrieveSummaryBeanByRelateRecord(
            $parentId,
            $parentModule,
            [
                'whereCriteria' => [
                    'status' => [
                        'value' => GAIConstants::SUMZ_STATUS_PROCESSING,
                        'operator' => 'equals',
                    ],
                ],
                'is_translate' => [
                    'value' => false,
                    'operator' => 'equals',
                ],
            ]
        );

        return ($summaryBean instanceof SugarBean) ? true : false;
    }

    /**
     * Delete summarization record by eval id
     *
     * @param string $evalId
     * @param string $error
     */
    public function deleteSummarizationByEvalId(string $evalId, $errorMessage)
    {
        $gaiBean = BeanFactory::newBean(GAIConstants::GAI_MODULE_NAME);

        $sq = new SugarQuery();
        $sq->select('id');
        $sq->from($gaiBean)
            ->where()
            ->equals('eval_id', $evalId)
            ->equals('deleted', 0);
        $sq->limit(1);

        $result = $gaiBean->fetchFromQuery($sq, ['id']);

        if (empty($result)) {
            return;
        }

        $gaiRecordId = array_keys($result)[0];

        $gaiRecordBean = BeanFactory::retrieveBean(GAIConstants::GAI_MODULE_NAME, $gaiRecordId);

        if (!$gaiRecordBean) {
            return;
        }

        $gaiRecordBean->deleted = 1;
        $gaiRecordBean->status = GAIConstants::SUMZ_STATUS_ERROR;
        $gaiRecordBean->error_message = $errorMessage;
        $gaiRecordBean->save();
    }

    /**
     * Update the summarization record with the response from GAI service
     *
     * @param array $response
     * @param SugarBean $gaiBean
     *
     * @return SugarBean
     */
    public function updateSummarization(array $response, SugarBean $gaiBean): SugarBean
    {
        if (array_key_exists('status', $response) && $response['status'] === GAIConstants::SUMZ_STATUS_ERROR) {
            $errorMessage = "Invalid response from GAI Service [Update]";

            if (array_key_exists('body', $response) && array_key_exists('pageData', $response['body'])) {
                $errorMessage .= ' ' . $response['body']['pageData'];
            }

            try {
                $errorResponse = json_encode($response);

                throw new SugarApiException('Invalid response from GAI Service [Update] -> ' . $errorResponse);
            } catch (\Exception $e) {
                throw new SugarApiException('Invalid response from GAI Service [Update]');
            }
        }

        if (!array_key_exists('body', $response) ||
            !array_key_exists('pageData', $response['body']) ||
            !is_array($response['body']['pageData']) ||
            !array_key_exists('choices', $response['body']['pageData']) ||
            safeCount($response['body']['pageData']['choices']) < 1
        ) {
            try {
                $errorMessage = json_encode($response);

                throw new SugarApiException('Invalid response from GAI Service [Update] -> ' . $errorMessage);
            } catch (\Exception $e) {
                throw new SugarApiException('Invalid response from GAI Service [Update]');
            }
        }

        $choices = $response['body']['pageData']['choices'][0];
        $message = $choices['message'];

        $gaiBean->summary = json_encode($message);

        if (array_key_exists('usage', $response['body']['pageData'])) {
            if (array_key_exists('prompt_tokens', $response['body']['pageData']['usage'])) {
                $gaiBean->prompt_tokens = $response['body']['pageData']['usage']['prompt_tokens'];
            }

            if (array_key_exists('completion_tokens', $response['body']['pageData']['usage'])) {
                $gaiBean->completion_tokens = $response['body']['pageData']['usage']['completion_tokens'];
            }
        }

        // last_sync_date should be the bean's created date, as that's when all its data was initially collected
        $gaiBean->last_sync_date = $gaiBean->date_entered;
        $gaiBean->status = GAIConstants::SUMZ_STATUS_COMPLETED;

        if ($gaiBean->parent_module === 'Cases') {
            $sentiment = '';

            if (array_key_exists('Sentiment', $message)) {
                $sentimentMessage = $message['Sentiment'];

                preg_match(
                    '/\b(Neutral|Frustrated|Satisfied)\b(?=\s*\(|$)/',
                    $sentimentMessage ?? '',
                    $matches
                );

                $sentiment = $matches[0] ?? '';

                if (!$gaiBean) {
                    throw new SugarApiExceptionNotFound();
                }

                $sentiment = strtolower($sentiment);
            }

            $parentRecord = BeanFactory::retrieveBean(
                $gaiBean->parent_module,
                $gaiBean->parent_id,
                ['disable_row_level_security' => true]
            );

            if ($parentRecord instanceof SugarBean &&
                isset($parentRecord->field_defs['case_ai_sentiment']) &&
                isset($parentRecord->field_defs['case_ai_date_generated']) &&
                (
                    $parentRecord->case_ai_sentiment !== $sentiment ||
                    $parentRecord->case_ai_date_generated || $gaiBean->date_entered
                )
            ) {
                $parentRecord->case_ai_sentiment = $sentiment;
                $parentRecord->case_ai_date_generated = $gaiBean->date_entered;
                $parentRecord->update_date_modified = false;
                $parentRecord->processed = true;
                $parentRecord->save();
            }

            $gaiBean->sentiment = $sentiment;
            $gaiBean->suggested_next_steps = '';
            $gaiBean->needed_followup = translate('LBL_GAI_N_A');
            $gaiBean->engaged_contacts = translate('LBL_GAI_N_A');

            if (array_key_exists('Suggested Next Steps', $message) && is_array($message['Suggested Next Steps'])) {
                $gaiBean->suggested_next_steps = json_encode($message['Suggested Next Steps']);
            }
        }

        if ($gaiBean->parent_module === 'Accounts') {
            if ($gaiBean->load_relationship('accounts_summz_gai')) {
                $relatedBeans = $gaiBean->accounts_summz_gai->getBeans();

                if (empty($relatedBeans)) {
                    $gaiBean->accounts_summz_gai->add($gaiBean->parent_id);
                }
            }

            $gaiBean->sentiment = 'na';
            $gaiBean->suggested_next_steps = '';
            $gaiBean->needed_followup = translate('LBL_GAI_N_A');
            $gaiBean->engaged_contacts = translate('LBL_GAI_N_A');

            $mapping = $choices['mapping'];
            $neededFollowupsIdentifier = 'neededFollowUps';
            $neededFollowupsKey = array_search($neededFollowupsIdentifier, $mapping);
            $needed_followup = [];
            if ($neededFollowupsKey) {
                $needed_followup[$neededFollowupsKey] = $choices['interactive'][$neededFollowupsKey];
            }

            $engagedContactsIdentifier = 'engagedContacts';
            $engagedContactsKey = array_search($engagedContactsIdentifier, $mapping);
            $engaged_contacts = [];
            if ($engagedContactsKey) {
                $engaged_contacts[$engagedContactsKey] = $choices['interactive'][$engagedContactsKey];
            }

            if (array_key_exists('Next Steps', $message) &&
                is_string($message['Next Steps']) && !empty($message['Next Steps']) > 0) {
                $gaiBean->suggested_next_steps = json_encode([$message['Next Steps']]);
            }

            if (!empty($needed_followup)) {
                $gaiBean->needed_followup = Helper::prepareAdditionalData('needed_followup', $needed_followup);
            }

            if ($gaiBean->parent_module === 'Accounts') {
                if ($this->accountHasContacts($gaiBean)) {
                    $gaiBean->engaged_contacts = Helper::prepareAdditionalData('engaged_contacts', $engaged_contacts);
                } else {
                    $noEngagedContacts = [];
                    $noEngagedContacts[$engagedContactsKey] = 'None';
                    $gaiBean->engaged_contacts = json_encode($noEngagedContacts);
                }
            }
        }

        if ($gaiBean->parent_module === 'Opportunities') {
            $gaiBean->sentiment = 'na';
            $gaiBean->needed_followup = translate('LBL_GAI_N_A');
            $gaiBean->engaged_contacts = translate('LBL_GAI_N_A');

            $gaiBean->suggested_next_steps = '';

            if (array_key_exists('Suggested Actions', $message) && is_array($message['Suggested Actions'])) {
                $gaiBean->suggested_next_steps = json_encode($message['Suggested Actions']);
            }
        }


        $gaiBean->save();

        return $gaiBean;
    }

    /**
     * Check if an account has related contacts
     *
     * @param SugarBean $gaiBean
     * @return boolean
     */
    protected function accountHasContacts($gaiBean)
    {
        $account = BeanFactory::getBean('Accounts', $gaiBean->parent_id);
        if ($account->load_relationship('contacts') && safeCount($account->contacts->getBeans()) > 0) {
            return true;
        }

        return false;
    }

    /**
    * Get the summarization record based on the criteria
    *
    * @param array $where ['field' => 'value']
    * @param array $selectFields ['field1', 'field2']
    * @param array $orderBy ['field' => 'field', 'direction' => 'ASC']
    *
    * @return SugarBean|boolean
    */
    public function getSummarizationBean(array $where, array $selectFields = [], array $orderBy = [])
    {
        $gaiBean = BeanFactory::newBean(GAIConstants::GAI_MODULE_NAME);

        $sq = new SugarQuery();
        $selectFields && safeCount($selectFields) > 0 ? $sq->select($selectFields) : $sq->select();
        $sq->from($gaiBean);

        foreach ($where as $field => $value) {
            $sq->where()->equals($field, $value);
        }

        $sq->where()->equals('deleted', 0);
        $sq->limit(1);

        if ($orderBy && safeCount($orderBy) > 0) {
            $sq->orderBy($orderBy['field'], $orderBy['direction']);
        }

        $result = $gaiBean->fetchFromQuery($sq);

        if (empty($result)) {
            return false;
        }

        $beanId = array_keys($result)[0];

        $data = $result[$beanId];

        return $data;
    }

    /**
     * Get the saved summarization details for the given criteria
     *
     * @param array $where - criteria to retrieve the summarization details
     * @return array|false - returns an array with summary, dateModified, and neededFollow
     */
    public function getSavedSummarizationDetails(array $where)
    {
        $selectFields = ['date_entered', 'summary', 'date_modified'];
        $orderBy = ['field' => 'date_entered', 'direction' => 'DESC'];

        $bean = $this->getSummarizationBean($where, $selectFields, $orderBy);
        if (empty($bean)) {
            return false;
        }

        $dateModified = Helper::formatDateAsIso($bean, 'date_modified');

        return [
            'summary' => $bean->summary,
            'dateModified' => $dateModified,
            'neededFollowup' => $bean->needed_followup,
        ];
    }
}
