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
 * Import attempt tracking for webhook and reconciliation idempotency.
 *
 * Stores Meta leadgen_id, status, retry count, errors, and SuiteCRM lead linkage.
 */
class UTSMImportTrackerService
{
    const STATUS_PENDING = 'pending';
    const STATUS_PROCESSING = 'processing';
    const STATUS_IMPORTED = 'imported';
    const STATUS_DUPLICATE = 'duplicate';
    const STATUS_FAILED = 'failed';
    const STATUS_SKIPPED = 'skipped';

    const SOURCE_WEBHOOK = 'webhook';
    const SOURCE_RECONCILIATION = 'reconciliation';

    /** @var DBManager */
    protected $db;

    public function __construct()
    {
        $this->db = DBManagerFactory::getInstance();
    }

    /**
     * @param string $leadgenId
     * @return array|null
     */
    public function getByLeadgenId($leadgenId)
    {
        if ($leadgenId === '') {
            return null;
        }
        $q = $this->db->quote($leadgenId);
        $res = $this->db->limitQuery(
            "SELECT * FROM ut_sm_lead_imports
             WHERE deleted = 0 AND leadgen_id = '{$q}'
             ORDER BY date_modified DESC",
            0,
            1
        );
        $row = $this->db->fetchByAssoc($res);
        return !empty($row['id']) ? $row : null;
    }

    /**
     * @param string $leadgenId
     * @return bool
     */
    public function isSuccessfullyImported($leadgenId)
    {
        $row = $this->getByLeadgenId($leadgenId);
        return !empty($row) && $row['import_status'] === self::STATUS_IMPORTED;
    }

    /**
     * @param array $data
     * @return string row id
     */
    public function upsertRecord(array $data)
    {
        $leadgenId = !empty($data['leadgen_id']) ? trim((string) $data['leadgen_id']) : '';
        if ($leadgenId === '') {
            return '';
        }

        $now = gmdate('Y-m-d H:i:s');
        $existing = $this->getByLeadgenId($leadgenId);
        $leadgenQ = $this->db->quote($leadgenId);
        $nowQ = $this->db->quote($now);

        $pageId = !empty($data['page_id']) ? $this->db->quote($data['page_id']) : 'NULL';
        $formId = !empty($data['form_id']) ? $this->db->quote($data['form_id']) : 'NULL';
        $accountId = !empty($data['account_id']) ? $this->db->quote($data['account_id']) : 'NULL';
        $metaCreated = !empty($data['meta_created_time']) ? $this->db->quote($data['meta_created_time']) : 'NULL';
        $status = !empty($data['import_status']) ? $this->db->quote($data['import_status']) : $this->db->quote(self::STATUS_PENDING);
        $source = !empty($data['import_source']) ? $this->db->quote($data['import_source']) : 'NULL';
        $leadId = !empty($data['lead_id']) ? $this->db->quote($data['lead_id']) : 'NULL';
        $contactId = !empty($data['contact_id']) ? $this->db->quote($data['contact_id']) : 'NULL';
        $error = isset($data['error_message']) ? $this->db->quote((string) $data['error_message']) : 'NULL';
        $retry = isset($data['retry_count']) ? (int) $data['retry_count'] : 0;

        if (!empty($existing['id'])) {
            $idQ = $this->db->quote($existing['id']);
            $retrySql = isset($data['retry_count'])
                ? (int) $data['retry_count']
                : ((int) $existing['retry_count'] + (!empty($data['increment_retry']) ? 1 : 0));

            $sets = array(
                "date_modified = '{$nowQ}'",
                "last_attempted = '{$nowQ}'",
                "import_status = '{$status}'",
                "retry_count = {$retrySql}",
            );
            if (!empty($data['page_id'])) {
                $sets[] = "page_id = '{$pageId}'";
            }
            if (!empty($data['form_id'])) {
                $sets[] = "form_id = '{$formId}'";
            }
            if (!empty($data['account_id'])) {
                $sets[] = "account_id = '{$accountId}'";
            }
            if (!empty($data['meta_created_time'])) {
                $sets[] = "meta_created_time = '{$metaCreated}'";
            }
            if (!empty($data['import_source'])) {
                $sets[] = "import_source = '{$source}'";
            }
            if (!empty($data['lead_id'])) {
                $sets[] = "lead_id = '{$leadId}'";
            }
            if (!empty($data['contact_id'])) {
                $sets[] = "contact_id = '{$contactId}'";
            }
            if (array_key_exists('error_message', $data)) {
                $sets[] = "error_message = " . ($error === 'NULL' ? 'NULL' : "'{$error}'");
            }

            $this->db->query(
                "UPDATE ut_sm_lead_imports SET " . implode(', ', $sets) . " WHERE id = '{$idQ}'"
            );
            return $existing['id'];
        }

        $id = create_guid();
        $idQ = $this->db->quote($id);
        $firstDetectedQ = !empty($data['first_detected']) ? $this->db->quote($data['first_detected']) : "'{$nowQ}'";

        $this->db->query(
            "INSERT INTO ut_sm_lead_imports
             (id, date_entered, date_modified, deleted, leadgen_id, page_id, form_id, account_id,
              meta_created_time, first_detected, last_attempted, import_status, retry_count,
              error_message, lead_id, contact_id, import_source)
             VALUES
             ('{$idQ}', '{$nowQ}', '{$nowQ}', 0, '{$leadgenQ}',
              " . ($pageId === 'NULL' ? 'NULL' : "'{$pageId}'") . ",
              " . ($formId === 'NULL' ? 'NULL' : "'{$formId}'") . ",
              " . ($accountId === 'NULL' ? 'NULL' : "'{$accountId}'") . ",
              " . ($metaCreated === 'NULL' ? 'NULL' : "'{$metaCreated}'") . ",
              {$firstDetectedQ}, '{$nowQ}', '{$status}', {$retry},
              " . ($error === 'NULL' ? 'NULL' : "'{$error}'") . ",
              " . ($leadId === 'NULL' ? 'NULL' : "'{$leadId}'") . ",
              " . ($contactId === 'NULL' ? 'NULL' : "'{$contactId}'") . ",
              " . ($source === 'NULL' ? 'NULL' : "'{$source}'") . ")"
        );

        return $id;
    }

    /**
     * @param string $leadgenId
     * @param array $context
     */
    public function markProcessing($leadgenId, array $context = array())
    {
        $data = array_merge($context, array(
            'leadgen_id' => $leadgenId,
            'import_status' => self::STATUS_PROCESSING,
        ));
        $this->upsertRecord($data);
    }

    /**
     * @param string $leadgenId
     * @param string $leadId
     * @param array $context
     */
    public function markImported($leadgenId, $leadId, array $context = array())
    {
        $data = array_merge($context, array(
            'leadgen_id' => $leadgenId,
            'import_status' => self::STATUS_IMPORTED,
            'lead_id' => $leadId,
            'error_message' => '',
        ));
        $this->upsertRecord($data);
    }

    /**
     * @param string $leadgenId
     * @param array $context
     */
    public function markDuplicate($leadgenId, array $context = array())
    {
        $data = array_merge($context, array(
            'leadgen_id' => $leadgenId,
            'import_status' => self::STATUS_DUPLICATE,
            'error_message' => '',
        ));
        $this->upsertRecord($data);
    }

    /**
     * @param string $leadgenId
     * @param string $message
     * @param array $context
     */
    public function markFailed($leadgenId, $message, array $context = array())
    {
        $data = array_merge($context, array(
            'leadgen_id' => $leadgenId,
            'import_status' => self::STATUS_FAILED,
            'error_message' => $message,
            'increment_retry' => true,
        ));
        $this->upsertRecord($data);
    }

    /**
     * @param string $leadgenId
     * @param string $reason
     * @param array $context
     */
    public function markSkipped($leadgenId, $reason, array $context = array())
    {
        $data = array_merge($context, array(
            'leadgen_id' => $leadgenId,
            'import_status' => self::STATUS_SKIPPED,
            'error_message' => $reason,
        ));
        $this->upsertRecord($data);
    }

    /**
     * Failed/pending rows to retry (most recent first).
     *
     * @param int $limit
     * @return array
     */
    public function getRetryableRecords($limit = 50)
    {
        $rows = array();
        $limit = max(1, (int) $limit);
        $failedQ = $this->db->quote(self::STATUS_FAILED);
        $pendingQ = $this->db->quote(self::STATUS_PENDING);
        $res = $this->db->limitQuery(
            "SELECT * FROM ut_sm_lead_imports
             WHERE deleted = 0
               AND import_status IN ('{$failedQ}', '{$pendingQ}')
               AND retry_count < 10
             ORDER BY last_attempted ASC",
            0,
            $limit
        );
        while ($row = $this->db->fetchByAssoc($res)) {
            $rows[] = $row;
        }
        return $rows;
    }
}
