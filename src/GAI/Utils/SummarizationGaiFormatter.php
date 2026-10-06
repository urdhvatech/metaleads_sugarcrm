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

namespace Sugarcrm\Sugarcrm\GAI\Utils;

use Sugarcrm\Sugarcrm\GAI\Constants\GAIConstants;

/**
 * Utility class for formatting SummarizationGai fields for export/display
 */
class SummarizationGaiFormatter
{
    /**
     * Get field name by column index from report definition
     * @param array $reportDef The report definition containing display_columns
     * @param int $index The column index
     * @return string The field name
     */
    public static function getFieldNameByIndex(array $reportDef, int $index): string
    {
        if (isset($reportDef['display_columns'][$index]['name'])) {
            return $reportDef['display_columns'][$index]['name'];
        }
        return '';
    }

    /**
     * Get field module by column index from report definition
     *
     * @param array $reportDef The report definition containing display_columns
     * @param int $index The column index
     * @return string The module name
     */
    public static function getFieldModuleByIndex(array $reportDef, int $index): string
    {
        if (isset($reportDef['display_columns'][$index]['table_key'])) {
            $tableKey = $reportDef['display_columns'][$index]['table_key'];

            if (!empty($tableKey)) {
                if (isset($reportDef['full_table_list'][$tableKey]['module'])) {
                    return $reportDef['full_table_list'][$tableKey]['module'];
                }
            }
        }
        return '';
    }

    /**
     * Check if the field is a SummarizationGai field that needs formatting
     * @param string $fieldName The field name
     * @param string $module The module where the field is
     * @return bool
     */
    public static function isSummarizationGaiField(string $fieldName, string $module): bool
    {
        if ($module !== GAIConstants::GAI_MODULE_NAME) {
            return false;
        }

        $summarizationFields = [
            'summary',
            'needed_followup',
            'engaged_contacts',
            'suggested_next_steps',
            'status',
        ];

        return in_array($fieldName, $summarizationFields);
    }

    /**
     * Format SummarizationGai field for human-readable output
     * @param mixed $value The field value
     * @param string $fieldName The field name
     * @return string The formatted value
     */
    public static function formatField($value, string $fieldName): string
    {
        if (empty($value) || !is_string($value)) {
            return '';
        }

        switch ($fieldName) {
            case 'engaged_contacts':
                return self::formatEngagedContacts($value);

            case 'needed_followup':
                return self::formatNeededFollowup($value);

            case 'suggested_next_steps':
                return self::formatSuggestedNextSteps($value);

            case 'summary':
                return self::formatSummary($value);

            case 'status':
                return self::getStatusLabel($value);

            default:
                return $value;
        }
    }

    /**
     * Get human-readable status label for a given status value
     * @param string $statusValue The raw status value
     * @return string The formatted status label
     */
    public static function getStatusLabel(string $statusValue): string
    {
        if (empty($statusValue)) {
            return $statusValue;
        }

        $statusLabels = [
            'failed' => translate('LBL_STATUS_FAILED', 'SummarizationGai'),
            'inProgress' => translate('LBL_STATUS_IN_PROGRESS', 'SummarizationGai'),
            'completed' => translate('LBL_STATUS_COMPLETED', 'SummarizationGai'),
            'error' => translate('LBL_STATUS_ERROR', 'SummarizationGai'),
            'readyForIngest' => translate('LBL_STATUS_READY_FOR_INGEST', 'SummarizationGai'),
            'ingestSuccess' => translate('LBL_STATUS_INGEST_SUCCESS', 'SummarizationGai'),
            'pending' => translate('LBL_STATUS_PENDING', 'SummarizationGai'),
            'onHold' => translate('LBL_STATUS_ON_HOLD', 'SummarizationGai'),
            'timeout' => translate('LBL_STATUS_TIMEOUT', 'SummarizationGai'),
            'notFound' => translate('LBL_STATUS_NOT_FOUND', 'SummarizationGai'),
        ];

        return $statusLabels[$statusValue] ?? $statusValue;
    }

    /**
     * Format engaged contacts field
     * @param string $value
     * @return string
     */
    public static function formatEngagedContacts(string $value): string
    {
        $data = json_decode($value, true);
        if (!is_array($data)) {
            return $value;
        }

        $formatted = [];

        // Find the key that contains engaged contacts data (could be "Engaged Contacts" or "Contacte Angajate (Engaged Contacts)")
        $contactsData = null;
        foreach ($data as $key => $content) {
            if ($key === 'Engaged Contacts' || strpos($key, '(Engaged Contacts)') !== false) {
                $contactsData = $content;
                break;
            }
        }

        if ($contactsData === null) {
            return $value;
        }

        // Handle the case where contactsData is a string (e.g., "None")
        if (is_string($contactsData)) {
            return $contactsData;
        }

        // Handle array case
        if (is_array($contactsData)) {
            foreach ($contactsData as $contact) {
                if (isset($contact['name']) && isset($contact['title'])) {
                    $formatted[] = $contact['name'] . ' (' . $contact['title'] . ')';
                }
            }
            return implode("\n", $formatted);
        }

        return $value;
    }

    /**
     * Format needed followup field
     * @param string $value
     * @return string
     */
    public static function formatNeededFollowup(string $value): string
    {
        $data = json_decode($value, true);
        if (!is_array($data)) {
            return $value;
        }

        $formatted = [];

        // Find the key that contains follow-ups data (could be "Needed Follow-ups" or "Urmăriri Necesare (Needed Follow-ups)")
        $followupsData = null;
        foreach ($data as $key => $content) {
            if ($key === 'Needed Follow-ups' || strpos($key, '(Needed Follow-ups)') !== false) {
                $followupsData = $content;
                break;
            }
        }

        if ($followupsData === null) {
            return $value;
        }

        // Handle the case where followupsData is a string (e.g., "None")
        if (is_string($followupsData)) {
            return $followupsData;
        }

        // Handle array case
        if (is_array($followupsData)) {
            foreach ($followupsData as $followup) {
                if (isset($followup['name']) && isset($followup['followUp'])) {
                    $formatted[] = $followup['name'] . ': ' . $followup['followUp'];
                }
            }
            return implode("\n\n", $formatted);
        }

        return $value;
    }

    /**
     * Format suggested next steps field
     * @param string $value
     * @return string
     */
    public static function formatSuggestedNextSteps(string $value): string
    {
        $data = json_decode($value, true);
        if (!is_array($data)) {
            return $value;
        }

        // Handle the case where data contains string values (e.g., ["None"] or direct string)
        if (count($data) === 1 && is_string($data[0])) {
            return $data[0];
        }

        return implode("\n\n", $data);
    }

    /**
     * Format summary field
     * @param string $value
     * @return string
     */
    public static function formatSummary(string $value): string
    {
        $data = json_decode($value, true);
        if (!is_array($data)) {
            return $value;
        }

        $formatted = [];

        // Simply iterate through all keys in the data and format them
        foreach ($data as $key => $content) {
            if (!empty($content)) {
                // Handle the case where content is just a string like "None"
                if (is_string($content)) {
                    if (strtolower($content) === 'none') {
                        $formatted[] = $key . ": " . $content;
                    } else {
                        $formatted[] = $key . "\n" . $content;
                    }
                } elseif (is_array($content)) {
                    // Handle array content
                    if (count($content) === 1 && is_string($content[0]) && strtolower($content[0]) === 'none') {
                        // Single "None" item in array - show with key for clarity
                        $formatted[] = $key . ": " . $content[0];
                    } else {
                        // Multiple items or non-"None" content
                        $contentText = implode("\n", $content);
                        $formatted[] = $key . "\n" . $contentText;
                    }
                } else {
                    $formatted[] = $key . "\n" . $content;
                }
            }
        }

        return implode("\n\n", $formatted);
    }
}
