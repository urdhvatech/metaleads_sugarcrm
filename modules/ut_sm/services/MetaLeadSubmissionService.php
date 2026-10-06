<?php
/**
 * This file is part of the "Meta Leads" package.
 *
 * @package Meta Leads
 * @author Urdhva Tech <contact@urdhva-tech.com>
 * @link https://www.urdhva-tech.com
 * @copyright Urdhva Tech
 * @license As specified in the License Agreement supplied with this package.
 */
if (!defined('sugarEntry') || !sugarEntry) {
    die('Not A Valid Entry Point');
}

/**
 * Idempotent Meta Lead Submission create/link helper.
 */
class UTSMMetaLeadSubmissionService
{
    /** @var DBManager */
    protected $db;

    public function __construct()
    {
        $this->db = DBManagerFactory::getInstance();
    }

    /**
     * Find existing submission by Meta leadgen id.
     *
     * @param string $leadgenId
     * @return string
     */
    public function findIdByLeadgenId($leadgenId)
    {
        $leadgenId = trim((string) $leadgenId);
        if ($leadgenId === '') {
            return '';
        }
        $q = $this->db->quote($leadgenId);
        $res = $this->db->limitQuery(
            "SELECT id FROM ut_metaleadsubmissions
             WHERE deleted = 0 AND meta_leadgen_id = '{$q}'
             ORDER BY date_entered DESC",
            0,
            1
        );
        $row = $this->db->fetchByAssoc($res);

        return !empty($row['id']) ? $row['id'] : '';
    }

    /**
     * Create submission if missing for this Meta leadgen id. Never updates historical rows.
     *
     * @param array $data
     * @return string submission id
     */
    public function ensureSubmission(array $data)
    {
        $leadgenId = !empty($data['meta_leadgen_id']) ? trim((string) $data['meta_leadgen_id']) : '';
        if ($leadgenId === '') {
            return '';
        }

        $existingId = $this->findIdByLeadgenId($leadgenId);
        if ($existingId !== '') {
            // Fill missing Lead/Contact links without rewriting historical field values.
            $updates = array();
            if (!empty($data['lead_id'])) {
                $updates['lead_id'] = (string) $data['lead_id'];
            }
            if (!empty($data['contact_id'])) {
                $updates['contact_id'] = (string) $data['contact_id'];
            }
            if (!empty($updates)) {
                /** @var ut_MetaLeadSubmissions $existing */
                $existing = BeanFactory::getBean('ut_MetaLeadSubmissions', $existingId);
                if (!empty($existing) && !empty($existing->id)) {
                    $changed = false;
                    if (!empty($updates['lead_id']) && empty($existing->lead_id)) {
                        $existing->lead_id = $updates['lead_id'];
                        $changed = true;
                    }
                    if (!empty($updates['contact_id']) && empty($existing->contact_id)) {
                        $existing->contact_id = $updates['contact_id'];
                        $changed = true;
                    }
                    if ($changed) {
                        $existing->save();
                    }
                }
            }
            return $existingId;
        }

        /** @var ut_MetaLeadSubmissions $bean */
        $bean = BeanFactory::newBean('ut_MetaLeadSubmissions');
        if (empty($bean)) {
            $GLOBALS['log']->fatal('ut_sm: unable to create ut_MetaLeadSubmissions bean');
            return '';
        }

        $formName = !empty($data['form_name']) ? trim((string) $data['form_name']) : '';
        $name = $formName !== '' ? $formName : ('Meta Lead ' . $leadgenId);
        if (strlen($name) > 150) {
            $name = substr($name, 0, 147) . '...';
        }

        $bean->name = $name;
        $bean->meta_leadgen_id = $leadgenId;
        $bean->form_id = !empty($data['form_id']) ? (string) $data['form_id'] : '';
        $bean->form_name = $formName;
        $bean->page_id = !empty($data['page_id']) ? (string) $data['page_id'] : '';
        $bean->page_name = !empty($data['page_name']) ? (string) $data['page_name'] : '';
        $bean->platform = !empty($data['platform']) ? (string) $data['platform'] : '';
        $bean->campaign_id = !empty($data['campaign_id']) ? (string) $data['campaign_id'] : '';
        $bean->campaign_name = !empty($data['campaign_name']) ? (string) $data['campaign_name'] : '';
        $bean->ad_id = !empty($data['ad_id']) ? (string) $data['ad_id'] : '';
        $bean->adset_id = !empty($data['adset_id']) ? (string) $data['adset_id'] : '';
        $bean->submitted_at = !empty($data['submitted_at']) ? (string) $data['submitted_at'] : '';
        if (isset($data['submitted_values']) || isset($data['field_data'])) {
            $raw = isset($data['field_data']) ? $data['field_data'] : $data['submitted_values'];
            // Always persist human-readable lines, never raw/HTML-encoded JSON.
            $bean->submitted_values = self::formatSubmittedValues($raw);
        }

        if (!empty($data['lead_id'])) {
            $bean->lead_id = (string) $data['lead_id'];
        }
        if (!empty($data['contact_id'])) {
            $bean->contact_id = (string) $data['contact_id'];
        }

        $bean->save();

        return !empty($bean->id) ? $bean->id : '';
    }

    /**
     * Convert Meta field_data (or JSON / mapped array) into readable text:
     * FULL_NAME : John Smith
     *
     * @param mixed $input
     * @return string
     */
    public static function formatSubmittedValues($input)
    {
        $pairs = self::extractLabelValuePairs($input);
        if (empty($pairs)) {
            if (is_string($input)) {
                return trim($input);
            }
            return '';
        }

        $lines = array();
        foreach ($pairs as $pair) {
            $lines[] = $pair['label'] . ' : ' . $pair['value'];
        }

        return implode("\n", $lines);
    }

    /**
     * HTML table for DetailView display (handles readable text or legacy JSON).
     *
     * @param mixed $input
     * @return string
     */
    public static function formatSubmittedValuesHtml($input)
    {
        $pairs = self::extractLabelValuePairs($input);
        if (empty($pairs) && is_string($input) && trim($input) !== '') {
            $escaped = htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
            return '<div class="ut-mls-submitted-values"><pre class="ut-mls-submitted-pre">'
                . $escaped . '</pre></div>';
        }
        if (empty($pairs)) {
            return '<div class="ut-mls-submitted-values ut-mls-empty">—</div>';
        }

        $rows = '';
        foreach ($pairs as $pair) {
            $label = htmlspecialchars($pair['label'], ENT_QUOTES, 'UTF-8');
            $value = htmlspecialchars($pair['value'], ENT_QUOTES, 'UTF-8');
            $rows .= '<tr><th scope="row">' . $label . '</th><td>' . $value . '</td></tr>';
        }

        return '<div class="ut-mls-submitted-values">'
            . '<table class="ut-mls-submitted-table" cellpadding="0" cellspacing="0">'
            . '<tbody>' . $rows . '</tbody></table></div>';
    }

    /**
     * @param mixed $input
     * @return array<int,array{label:string,value:string}>
     */
    public static function extractLabelValuePairs($input)
    {
        if (is_string($input)) {
            $normalized = self::normalizeStoredSubmittedValues($input);
            if ($normalized === '') {
                return array();
            }

            $decoded = json_decode($normalized, true);
            if (is_array($decoded)) {
                $input = $decoded;
            } elseif (self::looksLikeJsonString($normalized)) {
                // Broken/partial JSON — do not split on ":" as readable lines.
                return array();
            } else {
                return self::parseReadableLines($normalized);
            }
        }

        if (!is_array($input)) {
            return array();
        }

        // Meta field_data: [{name, values: []}, ...]
        if (self::looksLikeFieldData($input)) {
            $pairs = array();
            foreach ($input as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $name = isset($row['name']) ? trim((string) $row['name']) : '';
                if ($name === '') {
                    continue;
                }
                $values = isset($row['values']) && is_array($row['values']) ? $row['values'] : array();
                $valueParts = array();
                foreach ($values as $v) {
                    if (is_array($v) || is_object($v)) {
                        continue;
                    }
                    $v = trim(html_entity_decode((string) $v, ENT_QUOTES, 'UTF-8'));
                    if ($v !== '') {
                        $valueParts[] = $v;
                    }
                }
                if (empty($valueParts)) {
                    continue;
                }
                $pairs[] = array(
                    'label' => self::humanizeFieldLabel($name),
                    'value' => implode(', ', $valueParts),
                );
            }
            return $pairs;
        }

        // Flat mapped array: crm_field => value
        $pairs = array();
        foreach ($input as $key => $value) {
            if (!is_string($key) && !is_numeric($key)) {
                continue;
            }
            if (is_array($value) || is_object($value)) {
                continue;
            }
            $value = trim(html_entity_decode((string) $value, ENT_QUOTES, 'UTF-8'));
            if ($value === '') {
                continue;
            }
            $pairs[] = array(
                'label' => self::humanizeFieldLabel((string) $key),
                'value' => $value,
            );
        }

        return $pairs;
    }

    /**
     * Decode HTML entities / slashes so JSON like [{"name":"FULL_NAME"...}] parses.
     *
     * @param string $input
     * @return string
     */
    protected static function normalizeStoredSubmittedValues($input)
    {
        $text = trim((string) $input);
        if ($text === '') {
            return '';
        }

        // SuiteCRM / browsers may store or show &quot; instead of "
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        // In case it was double-encoded (&amp;quot;)
        if (strpos($text, '&quot;') !== false || strpos($text, '&#') !== false) {
            $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }
        $text = stripslashes($text);

        return trim($text);
    }

    /**
     * @param string $text
     * @return bool
     */
    protected static function looksLikeJsonString($text)
    {
        $text = ltrim($text);
        return $text !== '' && ($text[0] === '[' || $text[0] === '{');
    }

    /**
     * @param array $input
     * @return bool
     */
    protected static function looksLikeFieldData(array $input)
    {
        if (empty($input)) {
            return false;
        }
        $first = reset($input);
        return is_array($first) && (isset($first['name']) || isset($first['values']));
    }

    /**
     * Parse stored "LABEL : value" lines.
     *
     * @param string $text
     * @return array<int,array{label:string,value:string}>
     */
    protected static function parseReadableLines($text)
    {
        $pairs = array();
        $lines = preg_split('/\r\n|\r|\n/', $text);
        if (!is_array($lines)) {
            return array();
        }
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            if (preg_match('/^(.+?)\s*:\s*(.*)$/u', $line, $m)) {
                $pairs[] = array(
                    'label' => trim($m[1]),
                    'value' => trim($m[2]),
                );
            } else {
                $pairs[] = array(
                    'label' => 'VALUE',
                    'value' => $line,
                );
            }
        }
        return $pairs;
    }

    /**
     * FULL_NAME / full_name / email1 → readable label.
     *
     * @param string $name
     * @return string
     */
    public static function humanizeFieldLabel($name)
    {
        $name = trim((string) $name);
        if ($name === '') {
            return 'FIELD';
        }

        $upper = strtoupper($name);
        // Already SCREAMING_SNAKE from Meta
        if (preg_match('/^[A-Z0-9]+(?:_[A-Z0-9]+)*$/', $upper) && strpos($name, '_') !== false) {
            return $upper;
        }
        if (preg_match('/^[A-Z0-9]+$/', $upper) && strlen($name) <= 32) {
            return $upper;
        }

        $normalized = str_replace(array('-', '.'), '_', $name);
        $normalized = preg_replace('/([a-z])([A-Z])/', '$1_$2', $normalized);
        $normalized = preg_replace('/_+/', '_', (string) $normalized);
        $parts = explode('_', $normalized);
        $parts = array_map(function ($part) {
            return strtoupper((string) $part);
        }, $parts);

        return implode('_', array_filter($parts, function ($p) {
            return $p !== '';
        }));
    }
}
