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

require_once 'include/SugarSmarty/plugins/function.sugar_replace_vars.php';

class SugarFieldLink extends SugarFieldBase
{
    /**
     * {@inheritDoc}
     */
    public function apiFormatField(
        array       &$data,
        SugarBean   $bean,
        array       $args,
        $fieldName,
        $properties,
        ?array       $fieldList = null,
        ?ServiceBase $service = null
    ) {

        $this->ensureApiFormatFieldArguments($fieldList, $service);

        // this is only for generated links
        if (isset($bean->field_defs[$fieldName]['gen']) && isTruthy($bean->field_defs[$fieldName]['gen'])) {
            $subject = $bean->field_defs[$fieldName]['default'];
            if (!empty($subject)) {
                $beanArray = $this->sanitizeBeanArray($bean->toArray());
                $data[$fieldName] = replace_sugar_vars($subject, $beanArray, true);
            } else {
                $data[$fieldName] = '';
            }
        } else {
            parent::apiFormatField($data, $bean, $args, $fieldName, $properties, $fieldList, $service);
        }
    }

    /**
     * Sanitizes bean array by extracting values from Link2 objects
     *
     * @param array $beanArray The bean array to sanitize
     * @return array The sanitized bean array
     */
    private function sanitizeBeanArray(array $beanArray): array
    {
        return array_map(function ($value) {
            if ($value instanceof Link2) {
                return $this->extractLink2Value($value);
            }
            return $value;
        }, $beanArray);
    }

    /**
     * Extracts scalar values from all beans in a Link2 object
     *
     * @param Link2 $link The Link2 object to extract values from
     * @return string Comma-separated list of extracted values or empty string
     */
    private function extractLink2Value(Link2 $link): string
    {
        $relatedBeans = $link->getBeans();
        if (empty($relatedBeans)) {
            return '';
        }

        $values = [];
        foreach ($relatedBeans as $bean) {
            $value = $this->extractFieldFromBean($bean);
            if (!empty($value)) {
                $values[] = $value;
            }
        }

        return implode(', ', $values);
    }

    /**
     * Extracts a single field value from a bean
     *
     * @param SugarBean $bean The bean to extract from
     * @return string The extracted value or empty string if unable to extract
     */
    private function extractFieldFromBean($bean): string
    {
        // Special handling for EmailAddresses module as it often lacks a name/summary
        if ($bean->getModuleName() === 'EmailAddresses' && !empty($bean->email_address)) {
            return (string)$bean->email_address;
        }

        // Try to use summary text (safest for custom display logic)
        // This corresponds to what users see in Record View (e.g. Full Name, Account Name)
        $summary = $bean->get_summary_text();

        // Ensure we don't return the default SugarBean placeholder text
        if (!empty($summary) && $summary !== 'Base Implementation.  Should be overridden.') {
            return (string)$summary;
        }

        // Fallback to name field if summary was not useful
        if (!empty($bean->name)) {
            return (string)$bean->name;
        }

        // Fallback to email_address if everything else failed
        if (!empty($bean->email_address)) {
            return (string)$bean->email_address;
        }

        // Fall back to id field
        if (!empty($bean->id)) {
            return (string)$bean->id;
        }

        return '';
    }
}
