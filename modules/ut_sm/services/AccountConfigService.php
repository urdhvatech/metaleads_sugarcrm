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
require_once __DIR__ . '/AssignmentService.php';
require_once __DIR__ . '/FieldMappingService.php';

/**
 * Connected account configuration: lead forms, assignment settings, and field mapping sync.
 */
class UTSMAccountConfigService
{
    /** @var DBManager */
    protected $db;

    /** @var UTSMGraphClient */
    protected $graph;

    /** @var UTSMAssignmentService */
    protected $assignment;

    /** @var UTSMFieldMappingService */
    protected $fieldMapping;

    public function __construct()
    {
        $this->db = DBManagerFactory::getInstance();
        $this->graph = new UTSMGraphClient();
        $this->assignment = new UTSMAssignmentService();
        $this->fieldMapping = new UTSMFieldMappingService();
    }

    /**
     * @return UTSMAssignmentService
     */
    public function getAssignmentService()
    {
        return $this->assignment;
    }

    /**
     * @return UTSMFieldMappingService
     */
    public function getFieldMappingService()
    {
        return $this->fieldMapping;
    }

    /**
     * @param string $accountId CRM subscription id
     * @return array|null
     */
    public function getAccountById($accountId)
    {
        if ($accountId === '') {
            return null;
        }
        $idQ = $this->db->quote($accountId);
        $res = $this->db->limitQuery(
            "SELECT * FROM ut_sm_page_subscriptions WHERE deleted = 0 AND id = '{$idQ}'",
            0,
            1
        );
        $row = $this->db->fetchByAssoc($res);
        return !empty($row['id']) ? $row : null;
    }

    /**
     * Resolve account config for webhook page + platform.
     *
     * @param string $pageId
     * @param string $platform facebook|instagram
     * @return array|null
     */
    public function getAccountForWebhook($pageId, $platform = 'facebook')
    {
        if ($pageId === '') {
            return null;
        }
        $pageIdQ = $this->db->quote($pageId);
        $platform = ($platform === 'instagram') ? 'instagram' : 'facebook';
        $typeQ = $this->db->quote($platform);

        $res = $this->db->limitQuery(
            "SELECT * FROM ut_sm_page_subscriptions
             WHERE deleted = 0 AND page_id = '{$pageIdQ}' AND account_type = '{$typeQ}'",
            0,
            1
        );
        $row = $this->db->fetchByAssoc($res);
        if (!empty($row['id'])) {
            return $row;
        }

        // Fallback to Facebook page row (or any active row for this page)
        $res = $this->db->limitQuery(
            "SELECT * FROM ut_sm_page_subscriptions
             WHERE deleted = 0 AND page_id = '{$pageIdQ}'
             ORDER BY CASE WHEN account_type = 'facebook' THEN 0 ELSE 1 END",
            0,
            1
        );
        $row = $this->db->fetchByAssoc($res);
        return !empty($row['id']) ? $row : null;
    }

    /**
     * Resolve account for import, preferring the lead platform when its form is enabled.
     * If an Instagram lead hits an IG row without the form enabled, fall back to the
     * Facebook account for the same Page (shared leadgen forms).
     *
     * @param string $pageId
     * @param string $platform facebook|instagram
     * @param string $formId
     * @return array|null
     */
    public function resolveAccountForImport($pageId, $platform, $formId)
    {
        $preferred = $this->getAccountForWebhook($pageId, $platform);
        if (empty($preferred['id'])) {
            return null;
        }

        if ($this->isFormEnabledForImport($preferred['id'], $formId)) {
            return $preferred;
        }

        $altPlatform = ($platform === 'instagram') ? 'facebook' : 'instagram';
        $alt = $this->getAccountForWebhook($pageId, $altPlatform);
        if (
            !empty($alt['id'])
            && $alt['id'] !== $preferred['id']
            && $this->isFormEnabledForImport($alt['id'], $formId)
        ) {
            return $alt;
        }

        return $preferred;
    }

    /**
     * @return array
     */
    public function listConnectedAccounts()
    {
        $rows = array();
        $sql = "SELECT * FROM ut_sm_page_subscriptions WHERE deleted = 0 ORDER BY page_name ASC";
        $res = $this->db->query($sql);
        while ($row = $this->db->fetchByAssoc($res)) {
            $accountId = $row['id'];
            $activeForms = $this->countEnabledForms($accountId);
            $hasFormConfig = $this->hasFormConfiguration($accountId);
            $rows[] = array(
                'id' => $accountId,
                'page_id' => $row['page_id'],
                'page_name' => from_html($row['page_name']),
                'account_type' => !empty($row['account_type']) ? $row['account_type'] : 'facebook',
                'status' => 'Connected',
                'lead_forms_label' => $this->formatFormsLabel($activeForms, $hasFormConfig),
                'assignment_label' => $this->assignment->getDisplayLabel($row),
                'assignment_type' => !empty($row['assignment_type']) ? $row['assignment_type'] : 'keep_empty',
            );
        }
        return $rows;
    }

    /**
     * @param string $accountId
     * @return int
     */
    public function countEnabledForms($accountId)
    {
        $idQ = $this->db->quote($accountId);
        $c = $this->db->getOne(
            "SELECT COUNT(*) FROM ut_sm_account_forms
             WHERE deleted = 0 AND enabled = 1 AND account_id = '{$idQ}'"
        );
        return (int) $c;
    }

    /**
     * True if admin has refreshed/saved at least one form row for this account.
     *
     * @param string $accountId
     * @return bool
     */
    public function hasFormConfiguration($accountId)
    {
        $idQ = $this->db->quote($accountId);
        $c = $this->db->getOne(
            "SELECT COUNT(*) FROM ut_sm_account_forms
             WHERE deleted = 0 AND account_id = '{$idQ}'"
        );
        return ((int) $c) > 0;
    }

    /**
     * @param int $activeCount
     * @param bool $hasConfig
     * @return string
     */
    public function formatFormsLabel($activeCount, $hasConfig)
    {
        if (!$hasConfig) {
            return 'No Forms';
        }
        if ($activeCount <= 0) {
            return 'No Forms';
        }
        if ($activeCount === 1) {
            return '1 Active';
        }
        return $activeCount . ' Active';
    }

    /**
     * @param string $accountId
     * @return array
     */
    public function getFormsForAccount($accountId)
    {
        $forms = array();
        $idQ = $this->db->quote($accountId);
        $res = $this->db->query(
            "SELECT id, form_id, form_name, enabled
             FROM ut_sm_account_forms
             WHERE deleted = 0 AND account_id = '{$idQ}'
             ORDER BY form_name ASC"
        );
        while ($row = $this->db->fetchByAssoc($res)) {
            $forms[] = array(
                'id' => $row['id'],
                'form_id' => $row['form_id'],
                'form_name' => from_html($row['form_name']),
                'enabled' => !empty($row['enabled']),
            );
        }
        return $forms;
    }

    /**
     * Fetch Meta leadgen forms for the account's page; preserve enabled flags; new forms disabled.
     *
     * @param array $account
     * @return array{ok:bool,error?:string,count?:int}
     */
    public function refreshForms(array $account)
    {
        $pageId = !empty($account['page_id']) ? $account['page_id'] : '';
        $token = !empty($account['page_access_token']) ? $account['page_access_token'] : '';
        $accountId = !empty($account['id']) ? $account['id'] : '';
        if ($pageId === '' || $token === '' || $accountId === '') {
            return array('ok' => false, 'error' => 'Missing page token or account');
        }

        $api = $this->graph->getAll($pageId . '/leadgen_forms', array(
            'fields' => 'id,name,status',
            'limit' => 100,
            'access_token' => $token,
        ));

        if (!$api['ok']) {
            return array(
                'ok' => false,
                'error' => !empty($api['error']) ? $api['error'] : 'Failed to load lead forms',
            );
        }

        $items = isset($api['items']) && is_array($api['items']) ? $api['items'] : array();
        $now = gmdate('Y-m-d H:i:s');
        $seenFormIds = array();
        $count = 0;

        foreach ($items as $form) {
            $formId = isset($form['id']) ? trim((string) $form['id']) : '';
            $formName = isset($form['name']) ? trim((string) $form['name']) : '';
            if ($formId === '') {
                continue;
            }
            if ($formName === '') {
                $formName = 'Form ' . $formId;
            }
            $seenFormIds[] = $formId;
            $this->upsertForm($accountId, $formId, $formName, $now);
            $count++;
        }

        // Soft-delete forms Meta no longer returns (do not touch CRM leads)
        $this->softDeleteMissingForms($accountId, $seenFormIds, $now);

        // Sync field questions / mappings for each form (preserves admin choices)
        if (!empty($seenFormIds)) {
            $sync = $this->fieldMapping->syncFormsQuestions($account, $seenFormIds);
            if (!$sync['ok'] && !empty($sync['error'])) {
                $GLOBALS['log']->fatal('ut_sm form question sync issues: ' . $sync['error']);
            }
        }

        return array('ok' => true, 'count' => $count);
    }

    /**
     * Whether a lead from this form should be imported.
     * No form rows configured => allow all (backward compatible).
     *
     * @param string $accountId
     * @param string $formId
     * @return bool
     */
    public function isFormEnabledForImport($accountId, $formId)
    {
        if (!$this->hasFormConfiguration($accountId)) {
            return true;
        }
        if ($formId === '') {
            return false;
        }
        $accountIdQ = $this->db->quote($accountId);
        $formIdQ = $this->db->quote($formId);
        $enabled = $this->db->getOne(
            "SELECT enabled FROM ut_sm_account_forms
             WHERE deleted = 0 AND account_id = '{$accountIdQ}' AND form_id = '{$formIdQ}'"
        );
        return ((int) $enabled) === 1;
    }

    /**
     * @param string $accountId
     * @param array $data assignment_type, assignment_user_id, assignment_group_id, rr_user_ids[], enabled_form_ids[], field_map[]
     * @return array{ok:bool,error?:string}
     */
    public function saveConfiguration($accountId, array $data)
    {
        $account = $this->getAccountById($accountId);
        if (empty($account)) {
            return array('ok' => false, 'error' => 'Connected account not found');
        }

        $type = !empty($data['assignment_type']) ? $data['assignment_type'] : UTSMAssignmentService::TYPE_KEEP_EMPTY;
        $allowed = array(
            UTSMAssignmentService::TYPE_KEEP_EMPTY,
            UTSMAssignmentService::TYPE_ROUND_ROBIN,
            UTSMAssignmentService::TYPE_SPECIFIC_USER,
            UTSMAssignmentService::TYPE_SECURITY_GROUP,
        );
        if (!in_array($type, $allowed, true)) {
            $type = UTSMAssignmentService::TYPE_KEEP_EMPTY;
        }

        $userId = !empty($data['assignment_user_id']) ? trim((string) $data['assignment_user_id']) : '';
        $groupId = !empty($data['assignment_group_id']) ? trim((string) $data['assignment_group_id']) : '';
        $rrIds = isset($data['rr_user_ids']) && is_array($data['rr_user_ids']) ? $data['rr_user_ids'] : array();
        $rrJson = $this->assignment->encodeUserIds($rrIds);

        // Clear unused fields per type
        if ($type !== UTSMAssignmentService::TYPE_SPECIFIC_USER) {
            $userId = '';
        }
        if ($type !== UTSMAssignmentService::TYPE_SECURITY_GROUP) {
            $groupId = '';
        }
        if ($type !== UTSMAssignmentService::TYPE_ROUND_ROBIN) {
            $rrJson = '[]';
        }

        $nowQ = $this->db->quote(gmdate('Y-m-d H:i:s'));
        $idQ = $this->db->quote($accountId);
        $typeQ = $this->db->quote($type);
        $userIdQ = $this->db->quote($userId);
        $groupIdQ = $this->db->quote($groupId);
        $rrJsonQ = $this->db->quote($rrJson);

        $this->db->query(
            "UPDATE ut_sm_page_subscriptions
             SET assignment_type = '{$typeQ}',
                 assignment_user_id = " . ($userId === '' ? 'NULL' : "'{$userIdQ}'") . ",
                 assignment_group_id = " . ($groupId === '' ? 'NULL' : "'{$groupIdQ}'") . ",
                 assignment_rr_user_ids = '{$rrJsonQ}',
                 date_modified = '{$nowQ}'
             WHERE id = '{$idQ}'"
        );

        // Update enabled flags only for forms that exist for this account
        $enabledIds = isset($data['enabled_form_ids']) && is_array($data['enabled_form_ids'])
            ? $data['enabled_form_ids']
            : array();
        $enabledMap = array();
        foreach ($enabledIds as $fid) {
            $enabledMap[trim((string) $fid)] = true;
        }

        $forms = $this->getFormsForAccount($accountId);
        foreach ($forms as $form) {
            $enabled = !empty($enabledMap[$form['form_id']]) ? 1 : 0;
            $formRowIdQ = $this->db->quote($form['id']);
            $this->db->query(
                "UPDATE ut_sm_account_forms
                 SET enabled = {$enabled}, date_modified = '{$nowQ}'
                 WHERE id = '{$formRowIdQ}'"
            );
        }

        if (isset($data['field_map']) && is_array($data['field_map'])) {
            $this->fieldMapping->saveMappings($accountId, $data['field_map']);
        }

        return array('ok' => true);
    }

    /**
     * Ensure form questions/mappings are loaded for UI (no overwrite of crm_field).
     *
     * @param array $account
     * @return array forms with mappings + warnings
     */
    public function getFormsWithMappings(array $account)
    {
        $accountId = !empty($account['id']) ? $account['id'] : '';
        $forms = $this->getFormsForAccount($accountId);
        $result = array();

        foreach ($forms as $form) {
            if (!$this->fieldMapping->hasMappingsForForm($accountId, $form['form_id'])) {
                $this->fieldMapping->syncFormQuestions($account, $form['form_id']);
            }
            $mappings = $this->fieldMapping->getMappingsForForm($accountId, $form['form_id']);
            $warnings = $this->fieldMapping->getRequiredFieldWarnings($mappings);
            $result[] = array(
                'id' => $form['id'],
                'form_id' => $form['form_id'],
                'form_name' => $form['form_name'],
                'enabled' => $form['enabled'],
                'mappings' => $mappings,
                'has_mappings' => !empty($mappings),
                'warnings' => $warnings,
                'has_warnings' => !empty($warnings),
            );
        }

        return $result;
    }

    /**
     * @param string $accountId
     * @param string $formId
     * @param string $formName
     * @param string $now
     */
    protected function upsertForm($accountId, $formId, $formName, $now)
    {
        $accountIdQ = $this->db->quote($accountId);
        $formIdQ = $this->db->quote($formId);
        $formNameQ = $this->db->quote($formName);
        $nowQ = $this->db->quote($now);

        $check = $this->db->limitQuery(
            "SELECT id, enabled FROM ut_sm_account_forms
             WHERE account_id = '{$accountIdQ}' AND form_id = '{$formIdQ}'
             ORDER BY deleted ASC, date_modified DESC",
            0,
            1
        );
        $row = $this->db->fetchByAssoc($check);

        if (!empty($row['id'])) {
            $idQ = $this->db->quote($row['id']);
            // Preserve enabled; reactivate if soft-deleted
            $this->db->query(
                "UPDATE ut_sm_account_forms
                 SET form_name = '{$formNameQ}',
                     deleted = 0,
                     date_modified = '{$nowQ}'
                 WHERE id = '{$idQ}'"
            );
            return;
        }

        $id = create_guid();
        $idQ = $this->db->quote($id);
        $this->db->query(
            "INSERT INTO ut_sm_account_forms
             (id, date_entered, date_modified, deleted, account_id, form_id, form_name, enabled)
             VALUES
             ('{$idQ}', '{$nowQ}', '{$nowQ}', 0, '{$accountIdQ}', '{$formIdQ}', '{$formNameQ}', 0)"
        );
    }

    /**
     * @param string $accountId
     * @param array $activeFormIds
     * @param string $now
     */
    protected function softDeleteMissingForms($accountId, array $activeFormIds, $now)
    {
        $accountIdQ = $this->db->quote($accountId);
        $nowQ = $this->db->quote($now);
        if (empty($activeFormIds)) {
            $this->db->query(
                "UPDATE ut_sm_account_forms
                 SET deleted = 1, date_modified = '{$nowQ}'
                 WHERE deleted = 0 AND account_id = '{$accountIdQ}'"
            );
            return;
        }
        $quoted = array();
        foreach ($activeFormIds as $fid) {
            $quoted[] = "'" . $this->db->quote($fid) . "'";
        }
        $notIn = implode(',', $quoted);
        $this->db->query(
            "UPDATE ut_sm_account_forms
             SET deleted = 1, date_modified = '{$nowQ}'
             WHERE deleted = 0 AND account_id = '{$accountIdQ}' AND form_id NOT IN ({$notIn})"
        );
    }
}
