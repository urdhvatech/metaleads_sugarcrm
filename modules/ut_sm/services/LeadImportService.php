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

require_once 'modules/Administration/Administration.php';
require_once __DIR__ . '/GraphClient.php';
require_once __DIR__ . '/AccountConfigService.php';
require_once __DIR__ . '/AssignmentService.php';
require_once __DIR__ . '/FieldMappingService.php';
require_once __DIR__ . '/ImportTrackerService.php';
require_once __DIR__ . '/LicenseService.php';
require_once __DIR__ . '/MetaLeadSubmissionService.php';

/**
 * Shared Meta lead import pipeline for webhook and reconciliation.
 *
 * Fetches lead data from Graph API, applies form filters, field mapping,
 * duplicate detection (Lead → Contact → Account), assignment rules,
 * SuiteCRM Lead create/update, and Meta Lead Submission linkage.
 */
class UTSMLeadImportService
{
    /** @var DBManager */
    protected $db;

    /** @var array */
    protected $settings = array();

    /** @var UTSMGraphClient */
    protected $graph;

    /** @var UTSMAccountConfigService */
    protected $accountConfig;

    /** @var UTSMAssignmentService */
    protected $assignment;

    /** @var UTSMFieldMappingService */
    protected $fieldMapping;

    /** @var UTSMImportTrackerService */
    protected $tracker;

    /** @var UTSMMetaLeadSubmissionService */
    protected $submissions;

    public function __construct()
    {
        $this->db = DBManagerFactory::getInstance();
        $admin = new Administration();
        $admin->retrieveSettings('ut_sm');
        $this->settings = $admin->settings;
        $this->graph = new UTSMGraphClient();
        $this->accountConfig = new UTSMAccountConfigService();
        $this->assignment = new UTSMAssignmentService();
        $this->fieldMapping = new UTSMFieldMappingService();
        $this->tracker = new UTSMImportTrackerService();
        $this->submissions = new UTSMMetaLeadSubmissionService();
    }

    /**
     * Fetch a single lead from Meta Graph API.
     *
     * @param string $leadgenId
     * @param string $pageAccessToken
     * @return array{ok:bool,error?:string,error_code?:int,data?:array,token_source?:string}
     */
    public function fetchLeadFromApi($leadgenId, $pageAccessToken = '')
    {
        $fields = 'id,created_time,field_data,campaign_id,ad_id,adset_id,form_id,platform';
        $leadApi = $this->graphApiRequest($leadgenId, array('fields' => $fields), $pageAccessToken);
        $tokenSource = !empty($pageAccessToken) ? 'page_access_token' : 'app_saved_access_token';

        if (!$leadApi['ok'] && !empty($pageAccessToken)) {
            $leadApi = $this->graphApiRequest($leadgenId, array('fields' => $fields), '');
            $tokenSource = 'app_saved_access_token_fallback';
        }

        if (!$leadApi['ok']) {
            return array(
                'ok' => false,
                'error' => !empty($leadApi['error']) ? $leadApi['error'] : 'Lead fetch failed',
                'error_code' => isset($leadApi['error_code']) ? (int) $leadApi['error_code'] : 0,
                'token_source' => $tokenSource,
            );
        }

        return array(
            'ok' => true,
            'data' => (array) $leadApi['data'],
            'token_source' => $tokenSource,
        );
    }

    /**
     * Import a Meta lead using the same pipeline as the webhook.
     *
     * @param array $leadData Graph API lead object
     * @param array $options page_id, form_id, webhook_value, import_source, page_access_token
     * @return array{ok:bool,status:string,lead_id?:string,contact_id?:string,error?:string,is_new?:bool}
     */
    public function importMetaLead(array $leadData, array $options = array())
    {
        if (!UTSMLicenseService::isValid()) {
            return UTSMLicenseService::importBlockedResult();
        }

        $leadgenId = $this->sanitize(isset($leadData['id']) ? $leadData['id'] : '');
        if ($leadgenId === '') {
            return array('ok' => false, 'status' => 'failed', 'error' => 'Missing leadgen id');
        }

        $pageId = $this->sanitize(!empty($options['page_id']) ? $options['page_id'] : '');
        $formId = $this->sanitize(!empty($options['form_id']) ? $options['form_id'] : '');
        if ($formId === '' && !empty($leadData['form_id'])) {
            $formId = $this->sanitize($leadData['form_id']);
        }
        $importSource = !empty($options['import_source'])
            ? $options['import_source']
            : UTSMImportTrackerService::SOURCE_WEBHOOK;
        $pageAccessToken = !empty($options['page_access_token']) ? $options['page_access_token'] : '';
        $webhookValue = isset($options['webhook_value']) && is_array($options['webhook_value'])
            ? $options['webhook_value']
            : array();

        $metaCreated = $this->sanitize(isset($leadData['created_time']) ? $leadData['created_time'] : '');
        $trackerContext = array(
            'page_id' => $pageId,
            'form_id' => $formId,
            'meta_created_time' => $metaCreated,
            'import_source' => $importSource,
        );

        if ($this->tracker->isSuccessfullyImported($leadgenId)) {
            $trackerRow = $this->tracker->getByLeadgenId($leadgenId);
            $this->healSubmissionForExistingImport($leadgenId, $leadData, $options, $trackerRow);
            return array(
                'ok' => true,
                'status' => 'duplicate',
                'lead_id' => !empty($trackerRow['lead_id']) ? $trackerRow['lead_id'] : $this->findLeadIdByLeadgenId($leadgenId),
                'contact_id' => !empty($trackerRow['contact_id']) ? $trackerRow['contact_id'] : '',
            );
        }

        $existingCrmId = $this->findLeadIdByLeadgenId($leadgenId);
        if ($existingCrmId !== '') {
            $this->tracker->markDuplicate($leadgenId, array_merge($trackerContext, array('lead_id' => $existingCrmId)));
            $this->healSubmissionForExistingImport($leadgenId, $leadData, $options, array(
                'lead_id' => $existingCrmId,
            ));
            return array('ok' => true, 'status' => 'duplicate', 'lead_id' => $existingCrmId);
        }

        if ($this->submissions->findIdByLeadgenId($leadgenId) !== '') {
            $this->tracker->markDuplicate($leadgenId, $trackerContext);
            return array('ok' => true, 'status' => 'duplicate');
        }

        $this->tracker->markProcessing($leadgenId, $trackerContext);

        $source = $this->detectSource($webhookValue, $leadData);
        $account = $this->accountConfig->resolveAccountForImport($pageId, $source, $formId);
        $accountId = !empty($account['id']) ? $account['id'] : '';

        if ($accountId !== '' && !$this->accountConfig->isFormEnabledForImport($accountId, $formId)) {
            $this->tracker->markSkipped($leadgenId, 'Form not enabled for import', array_merge($trackerContext, array(
                'account_id' => $accountId,
            )));
            return array('ok' => true, 'status' => 'skipped');
        }

        try {
            $campaignId = $this->sanitize(isset($leadData['campaign_id']) ? $leadData['campaign_id'] : '');
            $adId = $this->sanitize(isset($leadData['ad_id']) ? $leadData['ad_id'] : '');
            $adsetId = $this->sanitize(isset($leadData['adset_id']) ? $leadData['adset_id'] : '');
            $campaignName = !empty($campaignId) ? $this->fetchCampaignName($campaignId, $pageAccessToken) : '';

            $mapped = $this->fieldMapping->mapFieldData(
                isset($leadData['field_data']) && is_array($leadData['field_data']) ? $leadData['field_data'] : array(),
                $accountId,
                $formId
            );
            $mapped['ut_leadgen_id'] = $leadgenId;
            $mapped['ut_form_id'] = $formId;
            $mapped['ut_campaign_external_id'] = $campaignId;
            $mapped['ut_ad_id'] = $adId;
            $mapped['ut_source_platform'] = $source;
            $mapped['ut_all_fields_json'] = json_encode($leadData);
            if (!empty($campaignName)) {
                $mapped['ut_campaign_name'] = $campaignName;
            }

            $resolve = $this->resolveCrmRecord($mapped, $account);
            $formName = $this->resolveFormName($accountId, $formId, $account);

            $submissionData = array(
                'meta_leadgen_id' => $leadgenId,
                'form_id' => $formId,
                'form_name' => $formName,
                'page_id' => $pageId !== '' ? $pageId : (!empty($account['page_id']) ? $account['page_id'] : ''),
                'page_name' => !empty($account['page_name']) ? $account['page_name'] : '',
                'platform' => $source,
                'campaign_id' => $campaignId,
                'campaign_name' => $campaignName,
                'ad_id' => $adId,
                'adset_id' => $adsetId,
                'submitted_at' => $this->normalizeMetaDatetime($metaCreated),
                'field_data' => isset($leadData['field_data']) && is_array($leadData['field_data'])
                    ? $leadData['field_data']
                    : $mapped,
                'lead_id' => !empty($resolve['lead_id']) ? $resolve['lead_id'] : '',
                'contact_id' => !empty($resolve['contact_id']) ? $resolve['contact_id'] : '',
            );
            $this->submissions->ensureSubmission($submissionData);

            $this->tracker->markImported($leadgenId, !empty($resolve['lead_id']) ? $resolve['lead_id'] : '', array_merge($trackerContext, array(
                'account_id' => $accountId,
                'lead_id' => !empty($resolve['lead_id']) ? $resolve['lead_id'] : '',
                'contact_id' => !empty($resolve['contact_id']) ? $resolve['contact_id'] : '',
            )));

            return array(
                'ok' => true,
                'status' => 'imported',
                'lead_id' => !empty($resolve['lead_id']) ? $resolve['lead_id'] : '',
                'contact_id' => !empty($resolve['contact_id']) ? $resolve['contact_id'] : '',
                'is_new' => !empty($resolve['is_new']),
                'match_type' => !empty($resolve['match_type']) ? $resolve['match_type'] : '',
            );
        } catch (Exception $e) {
            $msg = $e->getMessage();
            $this->tracker->markFailed($leadgenId, $msg, array_merge($trackerContext, array(
                'account_id' => $accountId,
            )));
            return array('ok' => false, 'status' => 'failed', 'error' => $msg);
        }
    }

    /**
     * @param string $leadgenId
     * @param string $pageId
     * @param string $formId
     * @param string $error
     * @param string $importSource
     */
    public function recordFetchFailure($leadgenId, $pageId, $formId, $error, $importSource = 'webhook')
    {
        $this->tracker->markFailed($leadgenId, $error, array(
            'page_id' => $pageId,
            'form_id' => $formId,
            'import_source' => $importSource,
        ));
    }

    /**
     * @return UTSMImportTrackerService
     */
    public function getTracker()
    {
        return $this->tracker;
    }

    /**
     * Lead → Contact → Account → new Lead.
     * When a Lead is used (matched or created), also attach a matching Contact
     * by email/phone so the Meta Lead Submission appears on both records.
     *
     * @param array $mapped
     * @param array|null $account connected Meta page row
     * @return array{lead_id?:string,contact_id?:string,is_new:bool,match_type:string}
     */
    protected function resolveCrmRecord(array $mapped, $account = null)
    {
        $leadId = $this->findMatchingLeadId($mapped);
        if ($leadId !== '') {
            $this->updateExistingLead($leadId, $mapped);
            $contactId = $this->findMatchingContactId($mapped);

            return array(
                'lead_id' => $leadId,
                'contact_id' => $contactId,
                'is_new' => false,
                'match_type' => 'lead',
            );
        }

        $contactId = $this->findMatchingContactId($mapped);
        if ($contactId !== '') {
            return array(
                'contact_id' => $contactId,
                'is_new' => false,
                'match_type' => 'contact',
            );
        }

        $crmAccountId = $this->findMatchingAccountId($mapped);
        $save = $this->createLead($mapped, $account, $crmAccountId);
        // New Lead: still link Contact if one matches after create (rare race),
        // and especially if email/phone match a Contact that should share history.
        $contactId = $this->findMatchingContactId($mapped);

        return array(
            'lead_id' => $save['lead_id'],
            'contact_id' => $contactId,
            'is_new' => true,
            'match_type' => $crmAccountId !== '' ? 'account' : 'new',
        );
    }

    /**
     * @param string $leadId
     * @param array $mapped
     */
    protected function updateExistingLead($leadId, array $mapped)
    {
        $lead = BeanFactory::getBean('Leads', $leadId);
        if (empty($lead) || empty($lead->id)) {
            return;
        }
        $this->applyMappedFieldsToLead($lead, $mapped, false);
        $lead->save();
    }

    /**
     * @param array $mapped
     * @param array|null $metaAccount
     * @param string $crmAccountId
     * @return array{lead_id:string}
     */
    protected function createLead(array $mapped, $metaAccount = null, $crmAccountId = '')
    {
        $lead = BeanFactory::newBean('Leads');
        $this->applyMappedFieldsToLead($lead, $mapped, true);

        if ($crmAccountId !== '') {
            $crmAccount = BeanFactory::getBean('Accounts', $crmAccountId);
            if (!empty($crmAccount) && !empty($crmAccount->id)) {
                $lead->account_id = $crmAccount->id;
                if (empty($lead->account_name) && !empty($crmAccount->name)) {
                    $lead->account_name = $crmAccount->name;
                }
            }
        }

        if (is_array($metaAccount) && !empty($metaAccount['id'])) {
            $this->assignment->applyToLead($lead, $metaAccount);
        }

        $lead->save();

        return array('lead_id' => $lead->id);
    }

    /**
     * @param SugarBean $lead
     * @param array $mapped
     * @param bool $isNew
     */
    protected function applyMappedFieldsToLead($lead, array $mapped, $isNew)
    {
        $systemKeys = array(
            'ut_leadgen_id' => true,
            'ut_form_id' => true,
            'ut_campaign_external_id' => true,
            'ut_ad_id' => true,
            'ut_source_platform' => true,
            'ut_all_fields_json' => true,
            'ut_campaign_name' => true,
        );

        foreach ($mapped as $field => $value) {
            if ($field === '' || !isset($value)) {
                continue;
            }
            if (!empty($systemKeys[$field])) {
                continue;
            }
            if ($field === 'email1' || $field === 'email2') {
                if ($value !== '') {
                    $lead->$field = $value;
                }
                continue;
            }
            if (array_key_exists($field, $lead->field_defs)) {
                $lead->$field = $value;
            }
        }

        $lead->ut_source_platform = isset($mapped['ut_source_platform']) ? $mapped['ut_source_platform'] : $lead->ut_source_platform;
        $lead->ut_campaign_external_id = isset($mapped['ut_campaign_external_id']) ? $mapped['ut_campaign_external_id'] : $lead->ut_campaign_external_id;
        $lead->ut_ad_id = isset($mapped['ut_ad_id']) ? $mapped['ut_ad_id'] : $lead->ut_ad_id;
        $lead->ut_form_id = isset($mapped['ut_form_id']) ? $mapped['ut_form_id'] : $lead->ut_form_id;
        $lead->ut_all_fields_json = isset($mapped['ut_all_fields_json']) ? $mapped['ut_all_fields_json'] : $lead->ut_all_fields_json;
        $lead->ut_leadgen_id = isset($mapped['ut_leadgen_id']) ? $mapped['ut_leadgen_id'] : $lead->ut_leadgen_id;
        if (!empty($mapped['ut_campaign_name'])) {
            $lead->ut_campaign_name = $mapped['ut_campaign_name'];
        }

        if (empty($lead->last_name)) {
            $lead->last_name = 'Unknown';
        }
        if ($isNew && empty($lead->lead_source) && !empty($mapped['ut_source_platform'])) {
            $lead->lead_source = ucfirst($mapped['ut_source_platform']);
        }
    }

    /**
     * @param string $leadgenId
     * @return string
     */
    public function findLeadIdByLeadgenId($leadgenId)
    {
        if ($leadgenId === '') {
            return '';
        }
        $q = $this->db->quote($leadgenId);
        $sql = "SELECT id FROM leads WHERE deleted = 0 AND ut_leadgen_id = '{$q}' ORDER BY date_entered DESC";
        $res = $this->db->limitQuery($sql, 0, 1);
        $row = $this->db->fetchByAssoc($res);
        return !empty($row['id']) ? $row['id'] : '';
    }

    /**
     * @param array $mapped
     * @return string
     */
    protected function findMatchingLeadId(array $mapped)
    {
        if (!empty($mapped['ut_leadgen_id'])) {
            $id = $this->findLeadIdByLeadgenId($mapped['ut_leadgen_id']);
            if ($id !== '') {
                return $id;
            }
        }

        $id = $this->findPersonIdByPhone('leads', $mapped);
        if ($id !== '') {
            return $id;
        }

        return $this->findPersonIdByEmail('Leads', $mapped);
    }

    /**
     * @param array $mapped
     * @return string
     */
    protected function findMatchingContactId(array $mapped)
    {
        $id = $this->findPersonIdByPhone('contacts', $mapped);
        if ($id !== '') {
            return $id;
        }

        return $this->findPersonIdByEmail('Contacts', $mapped);
    }

    /**
     * Match Accounts.name to mapped company / account_name (case-insensitive).
     *
     * @param array $mapped
     * @return string
     */
    protected function findMatchingAccountId(array $mapped)
    {
        $name = '';
        if (!empty($mapped['account_name'])) {
            $name = trim((string) $mapped['account_name']);
        } elseif (!empty($mapped['company'])) {
            $name = trim((string) $mapped['company']);
        }
        if ($name === '') {
            return '';
        }

        $nameQ = $this->db->quote($name);
        $sql = "SELECT id FROM accounts
                WHERE deleted = 0
                  AND LOWER(name) = LOWER('{$nameQ}')
                ORDER BY date_modified DESC";
        $res = $this->db->limitQuery($sql, 0, 1);
        $row = $this->db->fetchByAssoc($res);

        return !empty($row['id']) ? $row['id'] : '';
    }

    /**
     * @param string $table leads|contacts
     * @param array $mapped
     * @return string
     */
    protected function findPersonIdByPhone($table, array $mapped)
    {
        $phoneCandidates = array();
        foreach (array('phone_mobile', 'phone_work', 'phone_home', 'phone_other') as $phoneField) {
            if (!empty($mapped[$phoneField])) {
                $phoneCandidates[] = $mapped[$phoneField];
            }
        }
        if (!empty($mapped['phone'])) {
            $phoneCandidates[] = $mapped['phone'];
        }

        $table = ($table === 'contacts') ? 'contacts' : 'leads';

        foreach ($phoneCandidates as $phoneRaw) {
            $normalized = $this->normalizePhone($phoneRaw);
            if ($normalized === '') {
                continue;
            }
            $phoneQ = $this->db->quote($phoneRaw);
            $digitsQ = $this->db->quote($normalized);
            $sql = "SELECT id FROM {$table}
                    WHERE deleted = 0
                      AND (
                        phone_mobile = '{$phoneQ}'
                        OR phone_work = '{$phoneQ}'
                        OR REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(IFNULL(phone_mobile,''),'+',''),' ',''),'-',''),'(',''),')','') = '{$digitsQ}'
                        OR REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(IFNULL(phone_work,''),'+',''),' ',''),'-',''),'(',''),')','') = '{$digitsQ}'
                      )
                    ORDER BY date_modified DESC";
            $res = $this->db->limitQuery($sql, 0, 1);
            $row = $this->db->fetchByAssoc($res);
            if (!empty($row['id'])) {
                return $row['id'];
            }
        }

        return '';
    }

    /**
     * @param string $beanModule Leads|Contacts
     * @param array $mapped
     * @return string
     */
    protected function findPersonIdByEmail($beanModule, array $mapped)
    {
        $email = '';
        if (!empty($mapped['email1'])) {
            $email = $mapped['email1'];
        } elseif (!empty($mapped['email'])) {
            $email = $mapped['email'];
        }
        if ($email === '') {
            return '';
        }

        $moduleQ = $this->db->quote($beanModule);
        $emailCaps = strtoupper($this->db->quote(strtolower($email)));
        $personTable = ($beanModule === 'Contacts') ? 'contacts' : 'leads';
        $sql = "SELECT eabr.bean_id AS id
                FROM email_addr_bean_rel eabr
                INNER JOIN email_addresses ea ON ea.id = eabr.email_address_id
                INNER JOIN {$personTable} p ON p.id = eabr.bean_id AND p.deleted = 0
                WHERE eabr.deleted = 0
                  AND ea.deleted = 0
                  AND eabr.bean_module = '{$moduleQ}'
                  AND ea.email_address_caps = '{$emailCaps}'
                ORDER BY eabr.date_modified DESC";
        $res = $this->db->limitQuery($sql, 0, 1);
        $row = $this->db->fetchByAssoc($res);

        return !empty($row['id']) ? $row['id'] : '';
    }

    /**
     * Heal missing Submission rows for already-imported Meta leadgen ids (no duplicates).
     *
     * @param string $leadgenId
     * @param array $leadData
     * @param array $options
     * @param array|null $trackerRow
     */
    protected function healSubmissionForExistingImport($leadgenId, array $leadData, array $options, $trackerRow = null)
    {
        if ($this->submissions->findIdByLeadgenId($leadgenId) !== '') {
            return;
        }

        $pageId = $this->sanitize(!empty($options['page_id']) ? $options['page_id'] : '');
        $formId = $this->sanitize(!empty($options['form_id']) ? $options['form_id'] : '');
        if ($formId === '' && !empty($leadData['form_id'])) {
            $formId = $this->sanitize($leadData['form_id']);
        }

        $leadId = !empty($trackerRow['lead_id']) ? $trackerRow['lead_id'] : $this->findLeadIdByLeadgenId($leadgenId);
        $contactId = !empty($trackerRow['contact_id']) ? $trackerRow['contact_id'] : '';

        $this->submissions->ensureSubmission(array(
            'meta_leadgen_id' => $leadgenId,
            'form_id' => $formId,
            'page_id' => $pageId,
            'platform' => $this->detectSource(
                isset($options['webhook_value']) && is_array($options['webhook_value']) ? $options['webhook_value'] : array(),
                $leadData
            ),
            'campaign_id' => $this->sanitize(isset($leadData['campaign_id']) ? $leadData['campaign_id'] : ''),
            'ad_id' => $this->sanitize(isset($leadData['ad_id']) ? $leadData['ad_id'] : ''),
            'adset_id' => $this->sanitize(isset($leadData['adset_id']) ? $leadData['adset_id'] : ''),
            'submitted_at' => $this->normalizeMetaDatetime(
                $this->sanitize(isset($leadData['created_time']) ? $leadData['created_time'] : '')
            ),
            'field_data' => isset($leadData['field_data']) && is_array($leadData['field_data'])
                ? $leadData['field_data']
                : array(),
            'lead_id' => $leadId,
            'contact_id' => $contactId,
        ));
    }

    /**
     * @param string $accountId connected Meta account row id
     * @param string $formId
     * @param array|null $account
     * @return string
     */
    protected function resolveFormName($accountId, $formId, $account = null)
    {
        if ($accountId === '' || $formId === '') {
            return '';
        }
        $forms = $this->accountConfig->getFormsForAccount($accountId);
        foreach ($forms as $form) {
            if (!empty($form['form_id']) && (string) $form['form_id'] === (string) $formId) {
                return !empty($form['form_name']) ? (string) $form['form_name'] : '';
            }
        }
        return '';
    }

    /**
     * @param string $metaTime
     * @return string Y-m-d H:i:s or empty
     */
    protected function normalizeMetaDatetime($metaTime)
    {
        $metaTime = trim((string) $metaTime);
        if ($metaTime === '') {
            return gmdate('Y-m-d H:i:s');
        }
        $ts = strtotime($metaTime);
        if ($ts === false) {
            return gmdate('Y-m-d H:i:s');
        }
        return gmdate('Y-m-d H:i:s', $ts);
    }

    protected function normalizePhone($phone)
    {
        return preg_replace('/\D+/', '', (string) $phone);
    }

    protected function detectSource(array $webhookValue, array $leadData)
    {
        $candidates = array(
            isset($webhookValue['ad_platform']) ? $webhookValue['ad_platform'] : '',
            isset($webhookValue['publisher_platform']) ? $webhookValue['publisher_platform'] : '',
            isset($leadData['platform']) ? $leadData['platform'] : '',
            isset($leadData['publisher_platform']) ? $leadData['publisher_platform'] : '',
        );

        foreach ($candidates as $raw) {
            $source = strtolower(trim((string) $raw));
            if ($source === '') {
                continue;
            }
            if (strpos($source, 'instagram') !== false || $source === 'ig') {
                return 'instagram';
            }
            if (strpos($source, 'facebook') !== false || $source === 'fb') {
                return 'facebook';
            }
        }

        return 'facebook';
    }

    protected function fetchCampaignName($campaignId, $token = '')
    {
        $res = $this->graphApiRequest($campaignId, array('fields' => 'name'), $token);
        if (!$res['ok']) {
            return '';
        }
        return $this->sanitize(isset($res['data']['name']) ? $res['data']['name'] : '');
    }

    protected function graphApiRequest($endpoint, array $params = array(), $token = '')
    {
        $accessToken = !empty($token) ? $token : $this->getSetting('ut_sm_access_token');
        if (empty($accessToken)) {
            return array('ok' => false, 'error' => 'Missing access token', 'error_code' => 0);
        }

        $params['access_token'] = $accessToken;
        $res = $this->graph->get($endpoint, $params);
        if (!$res['ok']) {
            return array(
                'ok' => false,
                'error' => !empty($res['error']) ? $res['error'] : 'Graph API request failed',
                'error_code' => isset($res['error_code']) ? (int) $res['error_code'] : 0,
            );
        }

        return array('ok' => true, 'data' => $res['data']);
    }

    protected function getSetting($key)
    {
        return isset($this->settings[$key]) ? (string) $this->settings[$key] : '';
    }

    protected function sanitize($value)
    {
        if (!is_string($value) && !is_numeric($value)) {
            return '';
        }
        return trim((string) $value);
    }
}
