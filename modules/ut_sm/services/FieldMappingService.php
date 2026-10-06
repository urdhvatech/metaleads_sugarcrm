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

require_once __DIR__ . '/GraphClient.php';

/**
 * Per-form Meta field to SuiteCRM Lead field mapping with intelligent defaults.
 *
 * Syncs Meta form questions, suggests conservative default mappings, and applies
 * mappings during lead import without overwriting administrator customizations.
 */
class UTSMFieldMappingService
{
    const DONT_IMPORT = '';
    /** Split Meta full name into first_name + last_name */
    const CRM_FULL_NAME = '__full_name__';

    /** @var DBManager */
    protected $db;

    /** @var UTSMGraphClient */
    protected $graph;

    /** @var array|null */
    protected $leadFieldCache = null;

    public function __construct()
    {
        $this->db = DBManagerFactory::getInstance();
        $this->graph = new UTSMGraphClient();
    }

    /**
     * SuiteCRM Lead fields available in mapping dropdowns.
     *
     * @return array list of {name, label, type, required}
     */
    public function getMappableLeadFields()
    {
        if ($this->leadFieldCache !== null) {
            return $this->leadFieldCache;
        }

        $lead = BeanFactory::newBean('Leads');
        $modStrings = return_module_language($GLOBALS['current_language'], 'Leads');
        $appStrings = isset($GLOBALS['app_strings']) ? $GLOBALS['app_strings'] : return_application_language($GLOBALS['current_language']);

        $allowedTypes = array(
            'varchar', 'name', 'text', 'phone', 'email', 'url', 'date', 'datetime',
            'int', 'float', 'currency', 'enum', 'multienum', 'radioenum', 'bool',
        );
        $exclude = array(
            'id', 'deleted', 'date_entered', 'date_modified', 'modified_user_id',
            'created_by', 'assigned_user_id', 'team_id', 'team_set_id',
            'name', 'full_name', 'picture', 'photo', 'password',
            'contact_id', 'account_id', 'opportunity_id', 'campaign_id',
            'reports_to_id', 'converted', 'e_invite_status_fields',
            'event_status_name', 'event_invite_status',
        );

        $fields = array();
        foreach ($lead->field_defs as $name => $def) {
            if (!is_array($def) || empty($def['name'])) {
                continue;
            }
            $name = $def['name'];
            if (in_array($name, $exclude, true)) {
                continue;
            }
            if (!empty($def['source']) && $def['source'] === 'non-db' && $name !== 'email1' && $name !== 'email2') {
                continue;
            }
            $type = !empty($def['type']) ? $def['type'] : '';
            if (!in_array($type, $allowedTypes, true)) {
                continue;
            }
            if (!empty($def['auto_increment']) || (!empty($def['studio']) && $def['studio'] === false)) {
                continue;
            }

            $label = $name;
            if (!empty($def['vname'])) {
                $v = $def['vname'];
                if (!empty($modStrings[$v])) {
                    $label = $modStrings[$v];
                } elseif (!empty($appStrings[$v])) {
                    $label = $appStrings[$v];
                } else {
                    $label = translate($v, 'Leads');
                }
            }

            $fields[] = array(
                'name' => $name,
                'label' => $label,
                'type' => $type,
                'required' => !empty($def['required']),
            );
        }

        usort($fields, function ($a, $b) {
            return strcasecmp($a['label'], $b['label']);
        });

        $this->leadFieldCache = $fields;
        return $fields;
    }

    /**
     * Dropdown options including Don't Import and Name split.
     *
     * @return array list of {value, label}
     */
    public function getCrmFieldOptions()
    {
        $options = array(
            array(
                'value' => self::DONT_IMPORT,
                'label' => translate('LBL_UT_SM_DONT_IMPORT', 'ut_sm'),
            ),
            array(
                'value' => self::CRM_FULL_NAME,
                'label' => translate('LBL_UT_SM_MAP_FULL_NAME', 'ut_sm'),
            ),
        );
        foreach ($this->getMappableLeadFields() as $field) {
            $options[] = array(
                'value' => $field['name'],
                'label' => $field['label'],
            );
        }
        return $options;
    }

    /**
     * Normalize Meta key/label for matching.
     *
     * @param string $value
     * @return string
     */
    public function normalizeKey($value)
    {
        $value = strtolower(trim((string) $value));
        $value = str_replace(array('-', ' '), '_', $value);
        $value = preg_replace('/[^a-z0-9_]/', '', $value);
        $value = preg_replace('/_+/', '_', $value);
        return trim($value, '_');
    }

    /**
     * Suggest a SuiteCRM field for a Meta question. Empty = Don't import.
     *
     * @param string $key
     * @param string $label
     * @param string $type Meta question type (EMAIL, CUSTOM, …)
     * @return string
     */
    public function suggestDefaultCrmField($key, $label = '', $type = '')
    {
        $type = strtoupper(trim((string) $type));
        $byType = array(
            'FIRST_NAME' => 'first_name',
            'LAST_NAME' => 'last_name',
            'FULL_NAME' => self::CRM_FULL_NAME,
            'EMAIL' => 'email1',
            'WORK_EMAIL' => 'email1',
            'PHONE' => 'phone_mobile',
            'PHONE_OTP' => 'phone_mobile',
            'USER_PROVIDED_PHONE_NUMBER' => 'phone_mobile',
            'WHATSAPP_NUMBER' => 'phone_mobile',
            'WORK_PHONE_NUMBER' => 'phone_work',
            'COMPANY_NAME' => 'account_name',
            'JOB_TITLE' => 'title',
            'CITY' => 'primary_address_city',
            'STATE' => 'primary_address_state',
            'PROVINCE' => 'primary_address_state',
            'COUNTRY' => 'primary_address_country',
            'STREET_ADDRESS' => 'primary_address_street',
            'ADDRESS_LINE_TWO' => 'primary_address_street',
            'POST_CODE' => 'primary_address_postalcode',
            'ZIP' => 'primary_address_postalcode',
            'WEBSITE' => 'website',
            'DOB' => 'birthdate',
            'GENDER' => '',
            'CUSTOM' => '',
        );

        if ($type !== '' && array_key_exists($type, $byType)) {
            // CUSTOM falls through to key/label matching only when empty suggestion
            if ($type !== 'CUSTOM') {
                return $byType[$type];
            }
        }

        $candidates = array($this->normalizeKey($key), $this->normalizeKey($label));
        $exact = array(
            'first_name' => 'first_name',
            'firstname' => 'first_name',
            'last_name' => 'last_name',
            'lastname' => 'last_name',
            'full_name' => self::CRM_FULL_NAME,
            'fullname' => self::CRM_FULL_NAME,
            'name' => self::CRM_FULL_NAME,
            'email' => 'email1',
            'email_address' => 'email1',
            'emailaddress' => 'email1',
            'work_email' => 'email1',
            'phone' => 'phone_mobile',
            'phone_number' => 'phone_mobile',
            'phonenumber' => 'phone_mobile',
            'mobile' => 'phone_mobile',
            'mobile_phone' => 'phone_mobile',
            'mobile_number' => 'phone_mobile',
            'mobilenumber' => 'phone_mobile',
            'cell' => 'phone_mobile',
            'cell_phone' => 'phone_mobile',
            'work_phone' => 'phone_work',
            'office_phone' => 'phone_work',
            'whatsapp' => 'phone_mobile',
            'whatsapp_number' => 'phone_mobile',
            'company' => 'account_name',
            'company_name' => 'account_name',
            'companyname' => 'account_name',
            'account_name' => 'account_name',
            'job_title' => 'title',
            'jobtitle' => 'title',
            'title' => 'title',
            'city' => 'primary_address_city',
            'state' => 'primary_address_state',
            'province' => 'primary_address_state',
            'country' => 'primary_address_country',
            'address' => 'primary_address_street',
            'street' => 'primary_address_street',
            'street_address' => 'primary_address_street',
            'zip' => 'primary_address_postalcode',
            'zip_code' => 'primary_address_postalcode',
            'zipcode' => 'primary_address_postalcode',
            'postal_code' => 'primary_address_postalcode',
            'postalcode' => 'primary_address_postalcode',
            'website' => 'website',
            'web_site' => 'website',
            'url' => 'website',
            'description' => 'description',
            'message' => 'description',
            'comments' => 'description',
            'comment' => 'description',
            'birthdate' => 'birthdate',
            'date_of_birth' => 'birthdate',
            'dob' => 'birthdate',
            'department' => 'department',
        );

        foreach ($candidates as $norm) {
            if ($norm !== '' && isset($exact[$norm])) {
                return $exact[$norm];
            }
        }

        // Conservative phrase match on label only (avoid mapping custom questions)
        $labelNorm = $this->normalizeKey($label);
        if ($labelNorm !== '') {
            $phrases = array(
                'first_name' => 'first_name',
                'last_name' => 'last_name',
                'full_name' => self::CRM_FULL_NAME,
                'email_address' => 'email1',
                'phone_number' => 'phone_mobile',
                'mobile_number' => 'phone_mobile',
                'mobile_phone' => 'phone_mobile',
                'company_name' => 'account_name',
                'job_title' => 'title',
                'postal_code' => 'primary_address_postalcode',
                'zip_code' => 'primary_address_postalcode',
                'street_address' => 'primary_address_street',
            );
            foreach ($phrases as $phrase => $crm) {
                if ($labelNorm === $phrase) {
                    return $crm;
                }
            }
        }

        return self::DONT_IMPORT;
    }

    /**
     * Fetch Meta questions and upsert mapping rows; preserve admin crm_field choices.
     *
     * @param array $account
     * @param string $formId
     * @return array{ok:bool,error?:string,count?:int}
     */
    public function syncFormQuestions(array $account, $formId)
    {
        $token = !empty($account['page_access_token']) ? $account['page_access_token'] : '';
        $accountId = !empty($account['id']) ? $account['id'] : '';
        $formId = trim((string) $formId);
        if ($token === '' || $accountId === '' || $formId === '') {
            return array('ok' => false, 'error' => 'Missing account token or form id');
        }

        $api = $this->graph->get($formId, array(
            'fields' => 'id,name,questions',
            'access_token' => $token,
        ));
        if (!$api['ok']) {
            return array(
                'ok' => false,
                'error' => !empty($api['error']) ? $api['error'] : 'Failed to load form questions',
            );
        }

        $questions = array();
        if (!empty($api['data']['questions']) && is_array($api['data']['questions'])) {
            $questions = $api['data']['questions'];
        }

        $now = gmdate('Y-m-d H:i:s');
        $seenKeys = array();
        $count = 0;
        $sort = 0;

        foreach ($questions as $q) {
            $key = isset($q['key']) ? trim((string) $q['key']) : '';
            if ($key === '') {
                continue;
            }
            $label = isset($q['label']) ? trim((string) $q['label']) : '';
            $type = isset($q['type']) ? trim((string) $q['type']) : '';
            if ($label === '') {
                $label = $key;
            }
            $seenKeys[] = $key;
            $this->upsertMappingRow($accountId, $formId, $key, $label, $type, $sort, $now);
            $sort++;
            $count++;
        }

        $this->softDeleteMissingQuestions($accountId, $formId, $seenKeys, $now);

        return array('ok' => true, 'count' => $count);
    }

    /**
     * Sync questions for every form on the account.
     *
     * @param array $account
     * @param array $formIds
     * @return array{ok:bool,error?:string,forms?:int}
     */
    public function syncFormsQuestions(array $account, array $formIds)
    {
        $errors = array();
        $okCount = 0;
        foreach ($formIds as $formId) {
            $res = $this->syncFormQuestions($account, $formId);
            if ($res['ok']) {
                $okCount++;
            } else {
                $errors[] = $formId . ': ' . (!empty($res['error']) ? $res['error'] : 'failed');
            }
        }
        if ($okCount === 0 && !empty($errors)) {
            return array('ok' => false, 'error' => implode('; ', $errors));
        }
        return array('ok' => true, 'forms' => $okCount);
    }

    /**
     * @param string $accountId
     * @param string $formId
     * @return array
     */
    public function getMappingsForForm($accountId, $formId)
    {
        $rows = array();
        $accountIdQ = $this->db->quote($accountId);
        $formIdQ = $this->db->quote($formId);
        $res = $this->db->query(
            "SELECT id, meta_field_key, meta_field_label, meta_field_type, crm_field, sort_order
             FROM ut_sm_form_field_mappings
             WHERE deleted = 0 AND account_id = '{$accountIdQ}' AND form_id = '{$formIdQ}'
             ORDER BY sort_order ASC, meta_field_label ASC"
        );
        while ($row = $this->db->fetchByAssoc($res)) {
            $crm = isset($row['crm_field']) ? (string) $row['crm_field'] : self::DONT_IMPORT;
            $rows[] = array(
                'id' => $row['id'],
                'meta_field_key' => $row['meta_field_key'],
                'meta_field_label' => from_html($row['meta_field_label']),
                'meta_field_type' => $row['meta_field_type'],
                'crm_field' => $crm,
            );
        }
        return $rows;
    }

    /**
     * Whether any mapping rows exist for this form (configured).
     *
     * @param string $accountId
     * @param string $formId
     * @return bool
     */
    public function hasMappingsForForm($accountId, $formId)
    {
        $accountIdQ = $this->db->quote($accountId);
        $formIdQ = $this->db->quote($formId);
        $c = $this->db->getOne(
            "SELECT COUNT(*) FROM ut_sm_form_field_mappings
             WHERE deleted = 0 AND account_id = '{$accountIdQ}' AND form_id = '{$formIdQ}'"
        );
        return ((int) $c) > 0;
    }

    /**
     * Save posted mappings without changing Meta keys that were not posted.
     *
     * @param string $accountId
     * @param array $byForm form_id => [ meta_key => crm_field ]
     * @return array{ok:bool}
     */
    public function saveMappings($accountId, array $byForm)
    {
        $nowQ = $this->db->quote(gmdate('Y-m-d H:i:s'));
        $accountIdQ = $this->db->quote($accountId);
        $allowed = array(self::DONT_IMPORT => true, self::CRM_FULL_NAME => true);
        foreach ($this->getMappableLeadFields() as $field) {
            $allowed[$field['name']] = true;
        }

        foreach ($byForm as $formId => $map) {
            if (!is_array($map)) {
                continue;
            }
            $formIdQ = $this->db->quote(trim((string) $formId));
            foreach ($map as $metaKey => $crmField) {
                $metaKey = trim((string) $metaKey);
                $crmField = trim((string) $crmField);
                if ($metaKey === '') {
                    continue;
                }
                if (!isset($allowed[$crmField])) {
                    $crmField = self::DONT_IMPORT;
                }
                $metaKeyQ = $this->db->quote($metaKey);
                $crmFieldQ = $this->db->quote($crmField);
                $this->db->query(
                    "UPDATE ut_sm_form_field_mappings
                     SET crm_field = '{$crmFieldQ}', date_modified = '{$nowQ}'
                     WHERE deleted = 0
                       AND account_id = '{$accountIdQ}'
                       AND form_id = '{$formIdQ}'
                       AND meta_field_key = '{$metaKeyQ}'"
                );
            }
        }

        return array('ok' => true);
    }

    /**
     * Map Meta field_data using stored per-form mappings.
     * Falls back to built-in defaults when no mapping rows exist (backward compatible).
     *
     * @param array $fieldData
     * @param string $accountId
     * @param string $formId
     * @return array crm_field => value (plus first_name/last_name after full-name split)
     */
    public function mapFieldData(array $fieldData, $accountId, $formId)
    {
        $valuesByMetaKey = array();
        foreach ($fieldData as $row) {
            $name = trim((string) (isset($row['name']) ? $row['name'] : ''));
            $values = isset($row['values']) && is_array($row['values']) ? $row['values'] : array();
            $value = isset($values[0]) ? trim((string) $values[0]) : '';
            if ($name === '' || $value === '') {
                continue;
            }
            $valuesByMetaKey[$name] = $value;
        }

        if ($accountId === '' || $formId === '' || !$this->hasMappingsForForm($accountId, $formId)) {
            return $this->legacyMapFieldData($valuesByMetaKey);
        }

        $mapped = array();
        $mappings = $this->getMappingsForForm($accountId, $formId);
        foreach ($mappings as $row) {
            $key = $row['meta_field_key'];
            $crm = $row['crm_field'];
            if ($crm === self::DONT_IMPORT || $crm === '') {
                continue;
            }
            if (!isset($valuesByMetaKey[$key])) {
                continue;
            }
            $value = $valuesByMetaKey[$key];
            if ($crm === self::CRM_FULL_NAME) {
                $this->applyFullNameSplit($mapped, $value);
                continue;
            }
            $mapped[$crm] = $value;
        }

        return $mapped;
    }

    /**
     * Soft warnings when required Lead fields are not covered by mappings.
     *
     * @param array $mappings
     * @return array list of warning strings
     */
    public function getRequiredFieldWarnings(array $mappings)
    {
        $required = array();
        foreach ($this->getMappableLeadFields() as $field) {
            if (!empty($field['required'])) {
                $required[$field['name']] = $field['label'];
            }
        }
        if (empty($required)) {
            return array();
        }

        $covered = array();
        foreach ($mappings as $row) {
            $crm = isset($row['crm_field']) ? $row['crm_field'] : '';
            if ($crm === self::CRM_FULL_NAME) {
                $covered['first_name'] = true;
                $covered['last_name'] = true;
            } elseif ($crm !== '' && $crm !== self::DONT_IMPORT) {
                $covered[$crm] = true;
            }
        }

        $warnings = array();
        $msg = translate('LBL_UT_SM_REQUIRED_FIELD_UNMAPPED', 'ut_sm');
        foreach ($required as $name => $label) {
            if (empty($covered[$name])) {
                $warnings[] = sprintf($msg, $label);
            }
        }
        return $warnings;
    }

    /**
     * @param string $accountId
     * @param string $formId
     * @param string $key
     * @param string $label
     * @param string $type
     * @param int $sort
     * @param string $now
     */
    protected function upsertMappingRow($accountId, $formId, $key, $label, $type, $sort, $now)
    {
        $accountIdQ = $this->db->quote($accountId);
        $formIdQ = $this->db->quote($formId);
        $keyQ = $this->db->quote($key);
        $labelQ = $this->db->quote($label);
        $typeQ = $this->db->quote($type);
        $nowQ = $this->db->quote($now);
        $sort = (int) $sort;

        $check = $this->db->limitQuery(
            "SELECT id, crm_field, deleted FROM ut_sm_form_field_mappings
             WHERE account_id = '{$accountIdQ}' AND form_id = '{$formIdQ}' AND meta_field_key = '{$keyQ}'
             ORDER BY deleted ASC, date_modified DESC",
            0,
            1
        );
        $row = $this->db->fetchByAssoc($check);

        if (!empty($row['id'])) {
            $idQ = $this->db->quote($row['id']);
            // Preserve existing crm_field (including Don't import / custom choices)
            $this->db->query(
                "UPDATE ut_sm_form_field_mappings
                 SET meta_field_label = '{$labelQ}',
                     meta_field_type = '{$typeQ}',
                     sort_order = {$sort},
                     deleted = 0,
                     date_modified = '{$nowQ}'
                 WHERE id = '{$idQ}'"
            );
            return;
        }

        $defaultCrm = $this->suggestDefaultCrmField($key, $label, $type);
        $crmQ = $this->db->quote($defaultCrm);
        $id = create_guid();
        $idQ = $this->db->quote($id);
        $this->db->query(
            "INSERT INTO ut_sm_form_field_mappings
             (id, date_entered, date_modified, deleted, account_id, form_id,
              meta_field_key, meta_field_label, meta_field_type, crm_field, sort_order)
             VALUES
             ('{$idQ}', '{$nowQ}', '{$nowQ}', 0, '{$accountIdQ}', '{$formIdQ}',
              '{$keyQ}', '{$labelQ}', '{$typeQ}', '{$crmQ}', {$sort})"
        );
    }

    /**
     * Soft-delete mappings for questions Meta no longer returns (keep history).
     *
     * @param string $accountId
     * @param string $formId
     * @param array $activeKeys
     * @param string $now
     */
    protected function softDeleteMissingQuestions($accountId, $formId, array $activeKeys, $now)
    {
        $accountIdQ = $this->db->quote($accountId);
        $formIdQ = $this->db->quote($formId);
        $nowQ = $this->db->quote($now);

        if (empty($activeKeys)) {
            $this->db->query(
                "UPDATE ut_sm_form_field_mappings
                 SET deleted = 1, date_modified = '{$nowQ}'
                 WHERE deleted = 0 AND account_id = '{$accountIdQ}' AND form_id = '{$formIdQ}'"
            );
            return;
        }

        $quoted = array();
        foreach ($activeKeys as $k) {
            $quoted[] = "'" . $this->db->quote($k) . "'";
        }
        $notIn = implode(',', $quoted);
        $this->db->query(
            "UPDATE ut_sm_form_field_mappings
             SET deleted = 1, date_modified = '{$nowQ}'
             WHERE deleted = 0 AND account_id = '{$accountIdQ}' AND form_id = '{$formIdQ}'
               AND meta_field_key NOT IN ({$notIn})"
        );
    }

    /**
     * Legacy hardcoded mapping used when form has no mapping config yet.
     *
     * @param array $valuesByMetaKey
     * @return array
     */
    protected function legacyMapFieldData(array $valuesByMetaKey)
    {
        $mapped = array();
        foreach ($valuesByMetaKey as $name => $value) {
            $norm = strtolower(trim((string) $name));
            $crm = $this->suggestDefaultCrmField($norm, $norm, '');
            if ($crm === self::DONT_IMPORT || $crm === '') {
                continue;
            }
            if ($crm === self::CRM_FULL_NAME) {
                $this->applyFullNameSplit($mapped, $value);
                continue;
            }
            $mapped[$crm] = $value;
        }
        return $mapped;
    }

    /**
     * @param array $mapped
     * @param string $fullName
     */
    protected function applyFullNameSplit(array &$mapped, $fullName)
    {
        $parts = preg_split('/\s+/', trim((string) $fullName));
        if (empty($parts) || $parts[0] === '') {
            return;
        }
        if (empty($mapped['first_name'])) {
            $mapped['first_name'] = $parts[0];
        }
        if (count($parts) > 1 && empty($mapped['last_name'])) {
            $mapped['last_name'] = trim(implode(' ', array_slice($parts, 1)));
        } elseif (empty($mapped['last_name'])) {
            // SuiteCRM requires last_name; put whole name there if only one token
            $mapped['last_name'] = $parts[0];
            $mapped['first_name'] = '';
        }
    }
}
