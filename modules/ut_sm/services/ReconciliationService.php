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
require_once __DIR__ . '/LeadImportService.php';
require_once __DIR__ . '/ImportTrackerService.php';
require_once __DIR__ . '/LicenseService.php';

/**
 * Missed and failed Meta lead recovery via periodic API reconciliation.
 *
 * Scans configured forms within a rolling lookback window, retries failed imports,
 * and records run statistics for the admin settings screen.
 */
class UTSMReconciliationService
{
    const DEFAULT_LOOKBACK_HOURS = 24;
    const MAX_PAGES_PER_FORM = 20;
    const MAX_RETRY_BATCH = 50;

    /** @var Administration */
    protected $admin;

    /** @var DBManager */
    protected $db;

    /** @var UTSMGraphClient */
    protected $graph;

    /** @var UTSMAccountConfigService */
    protected $accountConfig;

    /** @var UTSMLeadImportService */
    protected $importService;

    public function __construct()
    {
        $this->admin = new Administration();
        $this->admin->retrieveSettings('ut_sm');
        $this->db = DBManagerFactory::getInstance();
        $this->graph = new UTSMGraphClient();
        $this->accountConfig = new UTSMAccountConfigService();
        $this->importService = new UTSMLeadImportService();
    }

    /**
     * @return int hours
     */
    public function getLookbackHours()
    {
        $hours = isset($this->admin->settings['ut_sm_reconciliation_hours'])
            ? (int) $this->admin->settings['ut_sm_reconciliation_hours']
            : self::DEFAULT_LOOKBACK_HOURS;
        if ($hours < 1) {
            $hours = self::DEFAULT_LOOKBACK_HOURS;
        }
        if ($hours > 168) {
            $hours = 168;
        }
        return $hours;
    }

    /**
     * @return array summary for admin UI
     */
    public function getLastRunSummary()
    {
        $s = $this->admin->settings;
        return array(
            'last_run' => !empty($s['ut_sm_reconciliation_last_run']) ? $s['ut_sm_reconciliation_last_run'] : '',
            'checked' => isset($s['ut_sm_reconciliation_last_checked']) ? (int) $s['ut_sm_reconciliation_last_checked'] : 0,
            'imported' => isset($s['ut_sm_reconciliation_last_imported']) ? (int) $s['ut_sm_reconciliation_last_imported'] : 0,
            'duplicate' => isset($s['ut_sm_reconciliation_last_duplicate']) ? (int) $s['ut_sm_reconciliation_last_duplicate'] : 0,
            'failed' => isset($s['ut_sm_reconciliation_last_failed']) ? (int) $s['ut_sm_reconciliation_last_failed'] : 0,
            'skipped' => isset($s['ut_sm_reconciliation_last_skipped']) ? (int) $s['ut_sm_reconciliation_last_skipped'] : 0,
            'retried' => isset($s['ut_sm_reconciliation_last_retried']) ? (int) $s['ut_sm_reconciliation_last_retried'] : 0,
            'last_error' => !empty($s['ut_sm_reconciliation_last_error']) ? $s['ut_sm_reconciliation_last_error'] : '',
            'lookback_hours' => $this->getLookbackHours(),
        );
    }

    /**
     * Run reconciliation for all configured forms and retry failed imports.
     *
     * @return array{ok:bool,stats?:array,error?:string}
     */
    public function run()
    {
        if (!UTSMLicenseService::isValid()) {
            $GLOBALS['log']->warn('ut_sm reconciliation skipped because license is not valid');

            return array(
                'ok' => false,
                'error' => 'License is not valid',
            );
        }

        $stats = array(
            'checked' => 0,
            'imported' => 0,
            'duplicate' => 0,
            'failed' => 0,
            'skipped' => 0,
            'retried' => 0,
        );
        $lastError = '';
        $sinceTs = time() - ($this->getLookbackHours() * 3600);

        try {
            $this->retryFailedLeads($stats, $lastError);
            $targets = $this->getReconciliationTargets();

            foreach ($targets as $target) {
                $leads = $this->fetchFormLeads($target, $sinceTs);
                if (is_array($leads) && isset($leads['_error'])) {
                    $stats['failed']++;
                    $formId = !empty($target['form_id']) ? $target['form_id'] : '';
                    $lastError = $leads['_error']
                        . ($formId !== '' ? ' (form_id=' . $formId . ')' : '');
                    continue;
                }
                if ($leads === false) {
                    $stats['failed']++;
                    continue;
                }

                foreach ($leads as $leadData) {
                    $stats['checked']++;
                    $result = $this->importService->importMetaLead($leadData, array(
                        'page_id' => $target['page_id'],
                        'form_id' => $target['form_id'],
                        'page_access_token' => $target['page_access_token'],
                        'import_source' => UTSMImportTrackerService::SOURCE_RECONCILIATION,
                    ));
                    $this->incrementStat($stats, $result);
                    if (!$result['ok'] && !empty($result['error'])) {
                        $lastError = $result['error'];
                    }
                }
            }
        } catch (Exception $e) {
            $lastError = $e->getMessage();
            $GLOBALS['log']->fatal('ut_sm reconciliation error: ' . $lastError);
        }

        $this->saveRunStats($stats, $lastError);

        return array('ok' => true, 'stats' => $stats, 'error' => $lastError);
    }

    /**
     * Ensure Active scheduler job exists (every 30 minutes).
     */
    public function ensureScheduler()
    {
        $jobName = 'function::utSmReconcileMetaLeads';
        $jobNameQ = $this->db->quote($jobName);
        $check = $this->db->limitQuery(
            "SELECT id FROM schedulers WHERE deleted = 0 AND job = '{$jobNameQ}'",
            0,
            1
        );
        $row = $this->db->fetchByAssoc($check);
        if (!empty($row['id'])) {
            $idQ = $this->db->quote($row['id']);
            $this->db->query("UPDATE schedulers SET status = 'Active' WHERE id = '{$idQ}'");
            return;
        }

        $scheduler = BeanFactory::newBean('Schedulers');
        $scheduler->name = 'Meta Lead Reconciliation';
        $scheduler->job = $jobName;
        $scheduler->date_time_start = '2015-01-01 00:00:01';
        $scheduler->date_time_end = null;
        $scheduler->job_interval = '*/30::*::*::*';
        $scheduler->status = 'Active';
        $scheduler->catch_up = '1';
        $scheduler->created_by = '1';
        $scheduler->modified_user_id = '1';
        $scheduler->save();
    }

    /**
     * @param array $stats
     * @param string $lastError
     */
    protected function retryFailedLeads(array &$stats, &$lastError)
    {
        $records = $this->importService->getTracker()->getRetryableRecords(self::MAX_RETRY_BATCH);
        foreach ($records as $row) {
            $leadgenId = !empty($row['leadgen_id']) ? $row['leadgen_id'] : '';
            if ($leadgenId === '') {
                continue;
            }

            $stats['retried']++;
            $stats['checked']++;

            $pageToken = $this->getPageAccessToken(!empty($row['page_id']) ? $row['page_id'] : '');
            $fetch = $this->importService->fetchLeadFromApi($leadgenId, $pageToken);
            if (!$fetch['ok']) {
                $error = !empty($fetch['error']) ? $fetch['error'] : 'Retry fetch failed';
                $errorCode = isset($fetch['error_code']) ? (int) $fetch['error_code'] : 0;
                $context = array(
                    'page_id' => !empty($row['page_id']) ? $row['page_id'] : '',
                    'form_id' => !empty($row['form_id']) ? $row['form_id'] : '',
                    'import_source' => UTSMImportTrackerService::SOURCE_RECONCILIATION,
                );

                // Permanent Graph failures (deleted/test leads, inaccessible objects) should not loop.
                if ($this->isPermanentLeadFetchError($error, $errorCode)) {
                    $stats['skipped']++;
                    $this->importService->getTracker()->markSkipped(
                        $leadgenId,
                        'Lead no longer available from Meta: ' . $error,
                        $context
                    );
                    continue;
                }

                $stats['failed']++;
                $lastError = $error;
                $this->importService->recordFetchFailure(
                    $leadgenId,
                    $context['page_id'],
                    $context['form_id'],
                    $lastError,
                    UTSMImportTrackerService::SOURCE_RECONCILIATION
                );
                continue;
            }

            $result = $this->importService->importMetaLead($fetch['data'], array(
                'page_id' => !empty($row['page_id']) ? $row['page_id'] : '',
                'form_id' => !empty($row['form_id']) ? $row['form_id'] : '',
                'page_access_token' => $pageToken,
                'import_source' => UTSMImportTrackerService::SOURCE_RECONCILIATION,
            ));
            $this->incrementStat($stats, $result);
            if (!$result['ok'] && !empty($result['error'])) {
                $lastError = $result['error'];
            }
        }
    }

    /**
     * Meta returns this when a leadgen id was deleted, was a disposable test lead,
     * or the current token can never load that object.
     *
     * @param string $error
     * @param int $errorCode
     * @return bool
     */
    protected function isPermanentLeadFetchError($error, $errorCode = 0)
    {
        $error = (string) $error;
        if ($error === '') {
            return false;
        }
        if (stripos($error, 'Unsupported get request') !== false) {
            return true;
        }
        if (stripos($error, 'does not exist') !== false) {
            return true;
        }
        // Graph error code 100 with (#33) / object missing is permanent for lead retries.
        if ((int) $errorCode === 100 && stripos($error, 'cannot be loaded') !== false) {
            return true;
        }
        return false;
    }

    /**
     * @return array
     */
    protected function getReconciliationTargets()
    {
        $targets = array();
        $res = $this->db->query(
            "SELECT a.id AS account_id, a.page_id, a.page_name, a.page_access_token, a.account_type,
                    f.form_id, f.form_name, f.enabled
             FROM ut_sm_page_subscriptions a
             LEFT JOIN ut_sm_account_forms f
               ON f.account_id = a.id AND f.deleted = 0
             WHERE a.deleted = 0
             ORDER BY a.page_name ASC, f.form_name ASC"
        );

        $accountsWithoutForms = array();
        while ($row = $this->db->fetchByAssoc($res)) {
            $accountId = $row['account_id'];
            if (empty($row['form_id'])) {
                $accountsWithoutForms[$accountId] = $row;
                continue;
            }

            if (!$this->accountConfig->hasFormConfiguration($accountId)) {
                $targets[] = array(
                    'account_id' => $accountId,
                    'page_id' => $row['page_id'],
                    'page_access_token' => $row['page_access_token'],
                    'form_id' => $row['form_id'],
                    'form_name' => from_html($row['form_name']),
                );
                continue;
            }

            if (!empty($row['enabled'])) {
                $targets[] = array(
                    'account_id' => $accountId,
                    'page_id' => $row['page_id'],
                    'page_access_token' => $row['page_access_token'],
                    'form_id' => $row['form_id'],
                    'form_name' => from_html($row['form_name']),
                );
            }
        }

        foreach ($accountsWithoutForms as $accountId => $row) {
            if ($this->accountConfig->hasFormConfiguration($accountId)) {
                continue;
            }
            $forms = $this->accountConfig->getFormsForAccount($accountId);
            if (!empty($forms)) {
                foreach ($forms as $form) {
                    $targets[] = array(
                        'account_id' => $accountId,
                        'page_id' => $row['page_id'],
                        'page_access_token' => $row['page_access_token'],
                        'form_id' => $form['form_id'],
                        'form_name' => $form['form_name'],
                    );
                }
                continue;
            }
            $apiForms = $this->fetchPageFormIds($row);
            foreach ($apiForms as $formId) {
                $targets[] = array(
                    'account_id' => $accountId,
                    'page_id' => $row['page_id'],
                    'page_access_token' => $row['page_access_token'],
                    'form_id' => $formId,
                    'form_name' => 'Form ' . $formId,
                );
            }
        }

        return $this->dedupeTargets($targets);
    }

    /**
     * @param array $targets
     * @return array
     */
    protected function dedupeTargets(array $targets)
    {
        $seen = array();
        $unique = array();
        foreach ($targets as $target) {
            $key = (!empty($target['account_id']) ? $target['account_id'] : '')
                . '|' . (!empty($target['form_id']) ? $target['form_id'] : '');
            if ($key === '|' || isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $unique[] = $target;
        }
        return $unique;
    }

    /**
     * @param array $accountRow
     * @return array form ids
     */
    protected function fetchPageFormIds(array $accountRow)
    {
        $pageId = !empty($accountRow['page_id']) ? $accountRow['page_id'] : '';
        $token = !empty($accountRow['page_access_token']) ? $accountRow['page_access_token'] : '';
        if ($pageId === '' || $token === '') {
            return array();
        }

        $api = $this->graph->getAll($pageId . '/leadgen_forms', array(
            'fields' => 'id',
            'limit' => 100,
            'access_token' => $token,
        ), 5);

        if (!$api['ok']) {
            return array();
        }

        $ids = array();
        foreach ($api['items'] as $item) {
            if (!empty($item['id'])) {
                $ids[] = (string) $item['id'];
            }
        }
        return $ids;
    }

    /**
     * @param array $target
     * @param int $sinceTs
     * @return array|false
     */
    protected function fetchFormLeads(array $target, $sinceTs)
    {
        $formId = !empty($target['form_id']) ? $target['form_id'] : '';
        $token = !empty($target['page_access_token']) ? $target['page_access_token'] : '';
        if ($formId === '' || $token === '') {
            return array();
        }

        $filtering = json_encode(array(
            array(
                'field' => 'time_created',
                'operator' => 'GREATER_THAN',
                'value' => $sinceTs,
            ),
        ));

        $api = $this->graph->getAll($formId . '/leads', array(
            'fields' => 'id,created_time,field_data,campaign_id,ad_id,adset_id,form_id,platform',
            'limit' => 100,
            'filtering' => $filtering,
            'access_token' => $token,
        ), self::MAX_PAGES_PER_FORM);

        if (!$api['ok']) {
            $err = !empty($api['error']) ? $api['error'] : 'unknown';
            $GLOBALS['log']->fatal(
                'ut_sm reconciliation form leads fetch failed'
                . ' | form_id=' . $formId
                . ' | error=' . $err
            );
            return array('_error' => $err);
        }

        return isset($api['items']) && is_array($api['items']) ? $api['items'] : array();
    }

    /**
     * @param string $pageId
     * @return string
     */
    protected function getPageAccessToken($pageId)
    {
        if ($pageId === '') {
            return '';
        }
        $pageIdQ = $this->db->quote($pageId);
        $res = $this->db->limitQuery(
            "SELECT page_access_token FROM ut_sm_page_subscriptions WHERE deleted = 0 AND page_id = '{$pageIdQ}'",
            0,
            1
        );
        $row = $this->db->fetchByAssoc($res);
        return !empty($row['page_access_token']) ? $row['page_access_token'] : '';
    }

    /**
     * @param array $stats
     * @param array $result
     */
    protected function incrementStat(array &$stats, array $result)
    {
        $status = !empty($result['status']) ? $result['status'] : 'failed';
        if (!isset($stats[$status])) {
            $stats['failed']++;
            return;
        }
        $stats[$status]++;
    }

    /**
     * @param array $stats
     * @param string $lastError
     */
    protected function saveRunStats(array $stats, $lastError = '')
    {
        $now = gmdate('Y-m-d H:i:s') . ' UTC';
        $this->admin->saveSetting('ut_sm', 'reconciliation_last_run', $now);
        $this->admin->saveSetting('ut_sm', 'reconciliation_last_checked', (string) $stats['checked']);
        $this->admin->saveSetting('ut_sm', 'reconciliation_last_imported', (string) $stats['imported']);
        $this->admin->saveSetting('ut_sm', 'reconciliation_last_duplicate', (string) $stats['duplicate']);
        $this->admin->saveSetting('ut_sm', 'reconciliation_last_failed', (string) $stats['failed']);
        $this->admin->saveSetting('ut_sm', 'reconciliation_last_skipped', (string) $stats['skipped']);
        $this->admin->saveSetting('ut_sm', 'reconciliation_last_retried', (string) $stats['retried']);
        $this->admin->saveSetting('ut_sm', 'reconciliation_last_error', $lastError);
        $this->admin->retrieveSettings('ut_sm', true);

        $GLOBALS['log']->info(
            'ut_sm reconciliation complete'
            . ' | checked=' . $stats['checked']
            . ' | imported=' . $stats['imported']
            . ' | duplicate=' . $stats['duplicate']
            . ' | failed=' . $stats['failed']
            . ' | skipped=' . $stats['skipped']
            . ' | retried=' . $stats['retried']
        );
    }
}
