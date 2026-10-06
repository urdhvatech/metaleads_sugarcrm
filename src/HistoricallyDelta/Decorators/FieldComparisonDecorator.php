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

namespace Sugarcrm\Sugarcrm\HistoricallyDelta\Decorators;

use BeanFactory;
use Exception;
use SugarApiExceptionNotFound;
use Error;
use SugarBean;
use Sugarcrm\Sugarcrm\HistoricallyDelta\Decorators\FieldDecoratorFactory;
use SugarQueryException;

class FieldComparisonDecorator
{
    /**
     * Decorate the historical data with current values and metadata.
     *
     * @param array $historicalData
     * @param string $module
     *
     * @return array
     *
     * @throws Exception
     * @throws SugarApiExceptionNotFound
     * @throws Error
     * @throws SugarQueryException
     */
    public function decorate(array $historicalData, string $module): array
    {
        $result = [];

        foreach ($historicalData as $recordId => $pastValues) {
            $bean = $this->retrieveSugarBean($module, $recordId);

            if ($bean === false) {
                continue;
            }

            $decoratedFields = [];

            foreach ($pastValues as $field => $pastValue) {
                if ($field === 'id' || !isset($bean->$field)) {
                    continue;
                }

                $currentValue = $bean->$field;
                $fieldDef = $bean->field_defs[$field];
                $decorator = FieldDecoratorFactory::getDecorator($fieldDef);
                $metadata = $decorator->decorate($pastValue, $currentValue);


                $decoratedFields[$field] = [
                    'past' => $pastValue,
                    'current' => $currentValue,
                    'metadata' => $this->normalizeMetadata($metadata),
                ];
            }

            $result[$recordId] = $decoratedFields;
        }

        return $result;
    }

    /**
     * Retrieve a SugarBean instance by module and record ID.
     *
     * @param string $module
     * @param string $recordId
     * @return false|SugarBean
     *
     * @throws SugarApiExceptionNotFound
     * @throws Error
     * @throws SugarQueryException
     */
    protected function retrieveSugarBean(string $module, string $recordId): false|SugarBean
    {
        $bean = BeanFactory::retrieveBean($module, $recordId);

        if (empty($bean) || empty($bean->id)) {
            return false;
        }

        return $bean;
    }

    /**
     * Normalize metadata to an array.
     *
     * @param mixed $metadata
     *
     * @return array
     */
    private function normalizeMetadata($metadata): array
    {
        if (is_object($metadata) && method_exists($metadata, 'toArray')) {
            return $metadata->toArray();
        }

        return (array)$metadata;
    }
}
