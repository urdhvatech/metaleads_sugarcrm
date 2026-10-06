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

/**
 * This class is responsible for ordering fields in a SugarBean response.
 */
class OrderedFieldsResponseFormatter implements ResponseFormatter
{
    private ResponseFormatter $responseFormatter;

    public function __construct(ResponseFormatter $responseFormatter)
    {
        $this->responseFormatter = $responseFormatter;
    }

    public function formatForApi(SugarBean $bean, array $fieldList = [], array $options = []): array
    {
        $beanFields = $this->responseFormatter->formatForApi($bean, $fieldList, $options);

        return $this->orderFields($beanFields, $fieldList);
    }

    /**
     * Order fields according to provided $fieldList.
     * Fields mentioned in $fieldList are moved to the top of the array and ordered.
     * Other fields are left in the original order but passed after fields from $fieldList.
     *
     * @param array $beanFields
     * @param array $fieldList
     * @return array
     */
    protected function orderFields(array $beanFields, array $fieldList): array
    {
        if (empty($fieldList) || empty($beanFields)) {
            return $beanFields;
        }

        $sortedData = [];

        // Move fields from $fieldList to the top
        foreach ($fieldList as $field) {
            if (is_object($field) || is_array($field)) {
                continue;
            }

            if (array_key_exists($field, $beanFields)) {
                $sortedData[$field] = $beanFields[$field];
            }
        }

        // Remaining fields a left in the original order but passed after
        // fields from fieldList
        foreach ($beanFields as $key => $value) {
            if (!array_key_exists($key, $sortedData)) {
                $sortedData[$key] = $value;
            }
        }

        return $sortedData;
    }
}
