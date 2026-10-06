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
    define('sugarEntry', true);
    require_once 'include/entryPoint.php';
}

require_once 'modules/Administration/Administration.php';
require_once 'modules/ut_sm/services/LeadImportService.php';
require_once 'modules/ut_sm/services/LicenseService.php';

class UTSMWebhookReceiver
{
    protected $db;
    protected $settings = array();
    /** @var UTSMLeadImportService */
    protected $importService;

    public function __construct()
    {
        $this->db = DBManagerFactory::getInstance();
        $admin = new Administration();
        $admin->retrieveSettings('ut_sm');
        $this->settings = $admin->settings;
        $this->importService = new UTSMLeadImportService();
    }

    public function handle()
    {
        $method = isset($_SERVER['REQUEST_METHOD']) ? strtoupper($_SERVER['REQUEST_METHOD']) : 'GET';
        if ($method === 'GET') {
            $this->handleVerify();
            return;
        }
        if ($method === 'POST') {
            $this->handleWebhook();
            return;
        }
        $this->jsonResponse(405, array('status' => 'error', 'message' => 'Method not allowed'));
    }

    protected function handleVerify()
    {
        $mode = isset($_GET['hub_mode']) ? $_GET['hub_mode'] : (isset($_GET['hub.mode']) ? $_GET['hub.mode'] : '');
        $token = isset($_GET['hub_verify_token']) ? $_GET['hub_verify_token'] : (isset($_GET['hub.verify_token']) ? $_GET['hub.verify_token'] : '');
        $challenge = isset($_GET['hub_challenge']) ? $_GET['hub_challenge'] : (isset($_GET['hub.challenge']) ? $_GET['hub.challenge'] : '');
        $verifyToken = $this->getSetting('ut_sm_verify_token');

        if ($mode === 'subscribe' && !empty($verifyToken) && hash_equals($verifyToken, (string) $token)) {
            http_response_code(200);
            echo (string) $challenge;
            sugar_cleanup(true);
        }

        http_response_code(403);
        echo 'Forbidden';
        sugar_cleanup(true);
    }

    protected function handleWebhook()
    {
        if (!UTSMLicenseService::isValid()) {
            $GLOBALS['log']->warn('ut_sm webhook: lead processing skipped because license is not valid');
            $this->jsonResponse(200, array('status' => 'ok', 'processed' => 0, 'message' => 'License not valid'));
        }

        $raw = file_get_contents('php://input');
        if (!$this->verifySignature($raw)) {
            $GLOBALS['log']->fatal('ut_sm webhook signature verification failed');
            $this->jsonResponse(403, array('status' => 'error', 'message' => 'Invalid signature'));
        }

        $payload = json_decode($raw, true);
        if (!is_array($payload)) {
            $this->jsonResponse(400, array('status' => 'error', 'message' => 'Invalid JSON payload'));
        }

        $processed = 0;
        $hadFetchFailure = false;
        $entries = isset($payload['entry']) && is_array($payload['entry']) ? $payload['entry'] : array();

        foreach ($entries as $entry) {
            $pageId = $this->sanitize(isset($entry['id']) ? $entry['id'] : '');
            $pageAccessToken = $this->getPageAccessToken($pageId);

            $changes = isset($entry['changes']) && is_array($entry['changes']) ? $entry['changes'] : array();
            foreach ($changes as $change) {
                if (!isset($change['field']) || $change['field'] !== 'leadgen') {
                    continue;
                }

                $value = isset($change['value']) && is_array($change['value']) ? $change['value'] : array();
                $leadgenId = $this->sanitize(isset($value['leadgen_id']) ? $value['leadgen_id'] : '');
                $formId = $this->sanitize(isset($value['form_id']) ? $value['form_id'] : '');
                if (empty($leadgenId)) {
                    continue;
                }

                $existingByLeadgen = $this->importService->findLeadIdByLeadgenId($leadgenId);
                if (!empty($existingByLeadgen)) {
                    $this->importService->getTracker()->markDuplicate($leadgenId, array(
                        'page_id' => $pageId,
                        'form_id' => $formId,
                        'lead_id' => $existingByLeadgen,
                        'import_source' => UTSMImportTrackerService::SOURCE_WEBHOOK,
                    ));
                    $processed++;
                    continue;
                }

                $leadApi = $this->importService->fetchLeadFromApi($leadgenId, $pageAccessToken);
                if (!$leadApi['ok']) {
                    $errorCode = isset($leadApi['error_code']) ? (int) $leadApi['error_code'] : 0;
                    $GLOBALS['log']->fatal(
                        'ut_sm webhook lead fetch failed'
                        . ' | page_id=' . $pageId
                        . ' | leadgen_id=' . $leadgenId
                        . ' | token_source=' . (!empty($leadApi['token_source']) ? $leadApi['token_source'] : 'unknown')
                        . ' | error_code=' . $errorCode
                        . ' | error=' . $leadApi['error']
                    );
                    if ($errorCode === 190) {
                        $GLOBALS['log']->fatal('ut_sm webhook: Facebook token invalid (code 190). Re-authorize or wait for scheduled refresh.');
                    }
                    $this->importService->recordFetchFailure(
                        $leadgenId,
                        $pageId,
                        $formId,
                        !empty($leadApi['error']) ? $leadApi['error'] : 'Lead fetch failed',
                        UTSMImportTrackerService::SOURCE_WEBHOOK
                    );
                    $hadFetchFailure = true;
                    continue;
                }

                $leadData = (array) $leadApi['data'];
                if (empty($formId) && !empty($leadData['form_id'])) {
                    $formId = $this->sanitize($leadData['form_id']);
                }

                $result = $this->importService->importMetaLead($leadData, array(
                    'page_id' => $pageId,
                    'form_id' => $formId,
                    'webhook_value' => $value,
                    'page_access_token' => $pageAccessToken,
                    'import_source' => UTSMImportTrackerService::SOURCE_WEBHOOK,
                ));

                if ($result['ok'] || in_array($result['status'], array('imported', 'duplicate', 'skipped'), true)) {
                    $processed++;
                } elseif ($result['status'] === 'failed') {
                    $hadFetchFailure = true;
                }
            }
        }

        if ($hadFetchFailure) {
            $this->jsonResponse(500, array(
                'status' => 'error',
                'message' => 'One or more lead fetches failed',
                'processed' => $processed,
            ));
        }

        $this->jsonResponse(200, array('status' => 'ok', 'processed' => $processed));
    }

    /**
     * @param string $raw
     * @return bool
     */
    protected function verifySignature($raw)
    {
        $appSecret = $this->getSetting('ut_sm_app_secret');
        if ($appSecret === '') {
            $GLOBALS['log']->fatal('ut_sm webhook: app secret not configured; rejecting POST');
            return false;
        }

        $header = '';
        if (!empty($_SERVER['HTTP_X_HUB_SIGNATURE_256'])) {
            $header = (string) $_SERVER['HTTP_X_HUB_SIGNATURE_256'];
        } elseif (function_exists('getallheaders')) {
            $headers = getallheaders();
            if (is_array($headers)) {
                foreach ($headers as $k => $v) {
                    if (strtolower($k) === 'x-hub-signature-256') {
                        $header = (string) $v;
                        break;
                    }
                }
            }
        }

        if ($header === '' || stripos($header, 'sha256=') !== 0) {
            return false;
        }

        $provided = substr($header, 7);
        $expected = hash_hmac('sha256', (string) $raw, $appSecret);
        return hash_equals($expected, $provided);
    }

    protected function getPageAccessToken($pageId)
    {
        if (empty($pageId)) {
            return '';
        }
        $pageId = $this->db->quote($pageId);
        $sql = "SELECT page_access_token FROM ut_sm_page_subscriptions WHERE deleted = 0 AND page_id = '{$pageId}'";
        $res = $this->db->limitQuery($sql, 0, 1);
        $row = $this->db->fetchByAssoc($res);
        return !empty($row['page_access_token']) ? $row['page_access_token'] : '';
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

    protected function jsonResponse($status, array $payload)
    {
        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode($payload);
        sugar_cleanup(true);
    }
}

$receiver = new UTSMWebhookReceiver();
$receiver->handle();
