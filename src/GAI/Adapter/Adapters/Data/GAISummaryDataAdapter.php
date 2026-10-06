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

namespace Sugarcrm\Sugarcrm\GAI\Adapter\Adapters\Data;

use BeanFactory;
use SugarBean;
use SugarDateTime;
use SugarQuery;
use SugarApiExceptionNotFound;

use Sugarcrm\Sugarcrm\GAI\Adapter\Adapters\GAIBaseDataAdapter;
use Sugarcrm\Sugarcrm\GAI\Traits\DataStructure\DataStructureBulkTrait;

class GAISummaryDataAdapter extends GAIBaseDataAdapter
{
    use DataStructureBulkTrait;

    /**
     * Retrieves data from the GAI service for the inference process
     */
    public function getInferenceData(): array
    {
        $this->payload = parent::getInferenceData();
        $module = $this->options['module'];
        $recordId = $this->options['recordId'];

        if (empty($recordId) && !empty($module)) {
            //todo: thrown api exceptio
            return [
                'error' => true,
                'message' => 'LBL_STH_WENT_WRONG',
            ];
        }

        $bean = BeanFactory::retrieveBean(
            $module,
            $recordId,
            ['disable_row_level_security' => true, 'use_cache' => false]
        );

        if (is_null($bean)) {
            throw new SugarApiExceptionNotFound(
                sprintf(
                    'Could not find parent record %s in module: %s',
                    $module,
                    $recordId
                )
            );
        }

        $moduleDataConfig = $this->options['moduleDataConfig'] ?? [];
        $relatedDataConfig = $this->options['relatedDataConfig'] ?? [];

        $parentObject = $this->buildParentObject($bean, $moduleDataConfig, $relatedDataConfig);

        $this->payload['evalData']['parentObject'] = $parentObject;

        return $this->payload;
    }

    /**
     * Retrieves data from the GAI service for the retrieval process
     */
    public function getRetrieveData(): array
    {
        $this->payload = parent::getRetrieveData();

        return $this->payload;
    }

    /**
     * Build the data to be ingested on server
     *
     * @param SugarBean $bean
     * @param array $moduleDataConfig
     * @param array $relatedDataConfig
     *
     * @return array
     */
    protected function buildParentObject(SugarBean $bean, array $moduleDataConfig, array $relatedDataConfig): array
    {
        $parentObject = [];
        $fieldsList = $moduleDataConfig;

        if (!is_array($moduleDataConfig) || safeCount($moduleDataConfig) < 1) {
            $fieldsList = $this->getFieldsName($bean);
        }

        foreach ($fieldsList as $field) {
            if (!empty($bean->{$field})) {
                $fieldValue = $bean->{$field};
                $parentObject[$field] = "{$fieldValue}";
            }
        }

        if (!empty($relatedDataConfig)) {
            $this->addRelatedData($bean, $relatedDataConfig, $parentObject);
        }

        return $parentObject;
    }

    /**
     * Add related data to the parent object
     *
     * @param SugarBean $bean
     * @param array $relatedDataConfig
     * @param array $parentObject
     */
    protected function addRelatedData(SugarBean $bean, array $relatedDataConfig, &$parentObject)
    {
        foreach ($relatedDataConfig as $config) {
            $relatedModule = $config['module'];
            if (!$relatedModule) {
                continue;
            }

            $relModuleData = $this->getRelatedModuleData($bean, $config);

            if (safeCount($relModuleData) > 0) {
                $parentObject[$relatedModule] = $relModuleData;
            }
        }
    }

    /**
     * Get related module data based on the relationship and configuration
     *
     * @param SugarBean $bean
     * @param array $config
     *
     * @return array
     */
    protected function getRelatedModuleData(SugarBean $bean, array $config): array
    {
        $relationship = $config['relationship'];
        $maxModifiedDate = null;
        $response = [];
        $limit = 10;

        if (!$relationship) {
            return $response;
        }

        if ($bean->load_relationship($relationship)) {
            $where = [];

            if ($config['maxDataRange']) {
                $maxModifiedDate = new SugarDateTime();
                $maxModifiedDate->modify('-' . $config['maxDataRange'] . ' days');
                $maxModifiedDateDb = $maxModifiedDate->asDbDate();

                //todo fix the problem on ArchivedEmailsLink.php
                //now the query is hardcoded so dynamically where as array is not supported for Emails module
                if ($config['module'] === 'Emails') {
                    $where = " emails.date_modified > \"{$maxModifiedDateDb}\" ";
                } else {
                    $where = [
                        'lhs_field' => 'date_modified',
                        'operator' => '>',
                        'rhs_value' => $maxModifiedDateDb,
                    ];
                }
            }
            if ($config['maxRecordCount'] && is_int($config['maxRecordCount'])) {
                $limit = $config['maxRecordCount'];
            }

            $relData = $bean->$relationship->getBeans(
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

            if ($relationship === 'archived_emails' && $config['module'] === 'Emails') {
                //we have to remove the emails from no-reply addresses
                $relData = $this->filterRelatedEmailsNoReply($relData);
            }

            $relFields = $config['fields'];

            foreach ($relData as $relBean) {
                $rel = [];

                if (!is_array($relFields) || safeCount($relFields) < 1) {
                    $relFields = $this->getFieldsName($relBean);
                }

                foreach ($relFields as $field) {
                    if (!empty($relBean->{$field})) {
                        $fieldValue = $relBean->{$field};
                        $rel[$field] = "{$fieldValue}";
                    }
                }

                $response [] = $rel;
            }
        }

        return $response;
    }

    /**
     * Filter out default denied fields like case_ai_sentiment, case_ai_date_generated and description_html
     *
     * @param SugarBean $bean
     * @return array
     */
    protected function getFieldsName(SugarBean $bean): array
    {
        $fieldDefinitions = $bean->getFieldDefinitions();
        $filteredKeys = [];
        $defaultDeniedFields = ['case_ai_sentiment', 'case_ai_date_generated', 'description_html'];

        foreach ($fieldDefinitions as $key => $def) {
            if (isset($def['name']) && in_array($def['name'], $defaultDeniedFields)) {
                continue;
            }

            if (!isset($def['type']) || $def['type'] !== 'link') {
                $filteredKeys[] = $key;
            }
        }

        return $filteredKeys;
    }
}
