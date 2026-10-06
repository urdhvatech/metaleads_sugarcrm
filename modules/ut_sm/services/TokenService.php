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

/**
 * OAuth token lifecycle, page sync, and leadgen webhook subscription management.
 *
 * Exchanges short-lived tokens for long-lived tokens, syncs connected pages,
 * subscribes pages to leadgen events, and ensures the token refresh scheduler job.
 */
class UTSMTokenService
{
    /** Refresh when fewer than this many seconds remain before expiry. */
    const REFRESH_THRESHOLD_SECONDS = 864000; // 10 days

    /** @var Administration */
    protected $admin;

    /** @var UTSMGraphClient */
    protected $graph;

    /** @var DBManager */
    protected $db;

    public function __construct()
    {
        $this->admin = new Administration();
        $this->admin->retrieveSettings('ut_sm');
        $this->graph = new UTSMGraphClient();
        $this->db = DBManagerFactory::getInstance();
    }

    /**
     * @param string $key
     * @return string
     */
    public function getSetting($key)
    {
        $full = (strpos($key, 'ut_sm_') === 0) ? $key : 'ut_sm_' . $key;
        return isset($this->admin->settings[$full]) ? (string) $this->admin->settings[$full] : '';
    }

    /**
     * Exchange authorization code for short-lived token, then long-lived, sync pages, subscribe, ensure scheduler.
     *
     * @param string $code
     * @param string $redirectUri
     * @return array{ok:bool,error?:string,count?:int}
     */
    public function completeOAuth($code, $redirectUri)
    {
        $appId = $this->getSetting('app_id');
        $appSecret = $this->getSetting('app_secret');
        if ($appId === '' || $appSecret === '' || $redirectUri === '') {
            return array('ok' => false, 'error' => 'Missing OAuth app configuration');
        }

        $short = $this->graph->post('oauth/access_token', array(
            'client_id' => $appId,
            'client_secret' => $appSecret,
            'redirect_uri' => $redirectUri,
            'code' => $code,
        ));

        if (!$short['ok'] || empty($short['data']['access_token'])) {
            return array(
                'ok' => false,
                'error' => !empty($short['error']) ? $short['error'] : 'Token exchange failed',
                'error_code' => isset($short['error_code']) ? $short['error_code'] : 0,
            );
        }

        return $this->persistLongLivedAndSync($short['data']['access_token']);
    }

    /**
     * Exchange short-lived (or current) token for long-lived, sync pages, subscribe leadgen, ensure scheduler.
     *
     * @param string $shortLivedToken
     * @return array{ok:bool,error?:string,count?:int}
     */
    public function persistLongLivedAndSync($shortLivedToken)
    {
        $long = $this->exchangeForLongLived($shortLivedToken);
        if (!$long['ok']) {
            $this->handleTokenError($long);
            return $long;
        }

        $token = $long['access_token'];
        $expiresIn = isset($long['expires_in']) ? (int) $long['expires_in'] : 5184000; // ~60 days default
        $expiresAt = gmdate('Y-m-d H:i:s', time() + max(3600, $expiresIn));

        $this->admin->saveSetting('ut_sm', 'access_token', $token);
        $this->admin->saveSetting('ut_sm', 'token_expires_at', $expiresAt);
        $this->admin->retrieveSettings('ut_sm', true);

        $sync = $this->syncPagesAndSubscribe($token);
        if (!$sync['ok']) {
            return $sync;
        }

        $this->ensureScheduler();

        return array('ok' => true, 'count' => isset($sync['count']) ? $sync['count'] : 0);
    }

    /**
     * @param string $token
     * @return array{ok:bool,error?:string,error_code?:int,access_token?:string,expires_in?:int}
     */
    public function exchangeForLongLived($token)
    {
        $appId = $this->getSetting('app_id');
        $appSecret = $this->getSetting('app_secret');
        if ($appId === '' || $appSecret === '' || $token === '') {
            return array('ok' => false, 'error' => 'Missing app credentials or token');
        }

        $res = $this->graph->post('oauth/access_token', array(
            'grant_type' => 'fb_exchange_token',
            'client_id' => $appId,
            'client_secret' => $appSecret,
            'fb_exchange_token' => $token,
        ));

        if (!$res['ok'] || empty($res['data']['access_token'])) {
            return array(
                'ok' => false,
                'error' => !empty($res['error']) ? $res['error'] : 'Long-lived token exchange failed',
                'error_code' => isset($res['error_code']) ? $res['error_code'] : 0,
            );
        }

        return array(
            'ok' => true,
            'access_token' => $res['data']['access_token'],
            'expires_in' => isset($res['data']['expires_in']) ? (int) $res['data']['expires_in'] : 5184000,
        );
    }

    /**
     * Refresh if missing, expired, or within threshold. Always re-syncs pages when refreshing.
     *
     * @param bool $force
     * @return array{ok:bool,error?:string,refreshed?:bool,count?:int,skipped?:bool}
     */
    public function refreshIfNeeded($force = false)
    {
        $token = $this->getSetting('access_token');
        if ($token === '') {
            return array('ok' => false, 'error' => 'No access token stored. Authorize with Facebook first.');
        }

        if (!$force && !$this->needsRefresh()) {
            // Still re-subscribe pages periodically is optional; skip exchange.
            return array('ok' => true, 'skipped' => true, 'refreshed' => false);
        }

        return $this->persistLongLivedAndSync($token);
    }

    /**
     * @return bool
     */
    public function needsRefresh()
    {
        $token = $this->getSetting('access_token');
        if ($token === '') {
            return true;
        }

        $expiresAt = $this->getSetting('token_expires_at');
        if ($expiresAt === '') {
            return true;
        }

        $expiresTs = strtotime($expiresAt . ' UTC');
        if ($expiresTs === false) {
            return true;
        }

        return ($expiresTs - time()) <= self::REFRESH_THRESHOLD_SECONDS;
    }

    /**
     * Paginate /me/accounts, upsert page tokens, soft-delete removed pages, subscribe leadgen.
     *
     * @param string $userAccessToken
     * @return array{ok:bool,error?:string,count?:int}
     */
    public function syncPagesAndSubscribe($userAccessToken)
    {
        $pagesRes = $this->graph->getAll('me/accounts', array(
            'fields' => 'id,name,access_token',
            'limit' => 100,
            'access_token' => $userAccessToken,
        ));

        if (!$pagesRes['ok']) {
            $this->handleTokenError($pagesRes);
            return array(
                'ok' => false,
                'error' => !empty($pagesRes['error']) ? $pagesRes['error'] : 'Failed to list pages',
                'error_code' => isset($pagesRes['error_code']) ? $pagesRes['error_code'] : 0,
            );
        }

        $pages = isset($pagesRes['items']) && is_array($pagesRes['items']) ? $pagesRes['items'] : array();
        $now = gmdate('Y-m-d H:i:s');
        $count = 0;
        $activeKeys = array();
        $subscribeErrors = array();

        foreach ($pages as $page) {
            $pageId = isset($page['id']) ? trim((string) $page['id']) : '';
            $pageName = isset($page['name']) ? trim((string) $page['name']) : '';
            $pageToken = isset($page['access_token']) ? trim((string) $page['access_token']) : '';
            if ($pageId === '' || $pageToken === '') {
                continue;
            }
            $activeKeys[] = $pageId . '|facebook';
            $this->upsertPageSubscription($pageId, $pageName, $pageToken, $now, 'facebook', '');

            $sub = $this->subscribePageToLeadgen($pageId, $pageToken);
            if (!$sub['ok']) {
                $subscribeErrors[] = $pageId . ': ' . $sub['error'];
                $GLOBALS['log']->fatal('ut_sm leadgen subscribe failed for page ' . $pageId . ': ' . $sub['error']);
            }

            // Linked Instagram business accounts (same Page webhook / forms)
            $igRes = $this->graph->get($pageId, array(
                'fields' => 'instagram_business_account{id,username,name}',
                'access_token' => $pageToken,
            ));
            if ($igRes['ok'] && !empty($igRes['data']['instagram_business_account']['id'])) {
                $ig = $igRes['data']['instagram_business_account'];
                $igId = trim((string) $ig['id']);
                $igUsername = !empty($ig['username']) ? '@' . ltrim((string) $ig['username'], '@') : '';
                if ($igUsername === '' && !empty($ig['name'])) {
                    $igUsername = trim((string) $ig['name']);
                }
                if ($igUsername === '') {
                    $igUsername = 'Instagram';
                }
                $activeKeys[] = $pageId . '|instagram';
                $this->upsertPageSubscription($pageId, $igUsername, $pageToken, $now, 'instagram', $igId);
            }

            $count++;
        }

        $this->softDeleteMissingAccounts($activeKeys, $now);

        if (!empty($subscribeErrors) && $count === 0) {
            return array('ok' => false, 'error' => 'Page leadgen subscribe failed: ' . implode('; ', $subscribeErrors));
        }

        return array('ok' => true, 'count' => $count);
    }

    /**
     * @param string $pageId
     * @param string $pageToken
     * @return array{ok:bool,error?:string}
     */
    public function subscribePageToLeadgen($pageId, $pageToken)
    {
        $res = $this->graph->post($pageId . '/subscribed_apps', array(
            'subscribed_fields' => 'leadgen',
            'access_token' => $pageToken,
        ));

        if (!$res['ok']) {
            return array(
                'ok' => false,
                'error' => !empty($res['error']) ? $res['error'] : 'subscribed_apps failed',
            );
        }

        return array('ok' => true);
    }

    /**
     * Clear user token, expiry, and all page tokens.
     */
    public function disconnect()
    {
        $this->admin->saveSetting('ut_sm', 'access_token', '');
        $this->admin->saveSetting('ut_sm', 'token_expires_at', '');
        $now = gmdate('Y-m-d H:i:s');
        $this->db->query(
            "UPDATE ut_sm_page_subscriptions
             SET page_access_token = '', deleted = 1, date_modified = '" . $this->db->quote($now) . "'
             WHERE deleted = 0"
        );
        $this->admin->retrieveSettings('ut_sm', true);
    }

    /**
     * Ensure an Active daily scheduler job exists for token refresh.
     */
    public function ensureScheduler()
    {
        $jobName = 'function::utSmRefreshFacebookTokens';
        $jobNameQ = $this->db->quote($jobName);
        $check = $this->db->limitQuery(
            "SELECT id FROM schedulers WHERE deleted = 0 AND job = '{$jobNameQ}'",
            0,
            1
        );
        $row = $this->db->fetchByAssoc($check);
        if (!empty($row['id'])) {
            // Ensure it stays Active
            $idQ = $this->db->quote($row['id']);
            $this->db->query("UPDATE schedulers SET status = 'Active' WHERE id = '{$idQ}'");
            return;
        }

        $scheduler = BeanFactory::newBean('Schedulers');
        $scheduler->name = 'UT SM Refresh Facebook Tokens';
        $scheduler->job = $jobName;
        $scheduler->date_time_start = '2015-01-01 00:00:01';
        $scheduler->date_time_end = null;
        $scheduler->job_interval = '0::2::*::*::*';
        $scheduler->status = 'Active';
        $scheduler->catch_up = '1';
        $scheduler->created_by = '1';
        $scheduler->modified_user_id = '1';
        $scheduler->save();
    }

    /**
     * @param array $result
     */
    protected function handleTokenError(array $result)
    {
        $code = isset($result['error_code']) ? (int) $result['error_code'] : 0;
        if ($code === 190) {
            $GLOBALS['log']->fatal(
                'ut_sm Facebook token invalid/expired (code 190): '
                . (!empty($result['error']) ? $result['error'] : 'unknown')
            );
            $this->admin->saveSetting('ut_sm', 'access_token', '');
            $this->admin->saveSetting('ut_sm', 'token_expires_at', '');
            $this->admin->retrieveSettings('ut_sm', true);
        }
    }

    /**
     * @param string $pageId
     * @param string $pageName
     * @param string $pageToken
     * @param string $now
     * @param string $accountType facebook|instagram
     * @param string $instagramAccountId
     */
    protected function upsertPageSubscription($pageId, $pageName, $pageToken, $now, $accountType = 'facebook', $instagramAccountId = '')
    {
        $pageIdQ = $this->db->quote($pageId);
        $pageNameQ = $this->db->quote($pageName);
        $pageTokenQ = $this->db->quote($pageToken);
        $nowQ = $this->db->quote($now);
        $typeQ = $this->db->quote($accountType === 'instagram' ? 'instagram' : 'facebook');
        $igIdQ = $this->db->quote($instagramAccountId);

        $checkSql = "SELECT id FROM ut_sm_page_subscriptions
                     WHERE deleted = 0 AND page_id = '{$pageIdQ}' AND account_type = '{$typeQ}'";
        $checkRes = $this->db->limitQuery($checkSql, 0, 1);
        $row = $this->db->fetchByAssoc($checkRes);

        if (!empty($row['id'])) {
            $idQ = $this->db->quote($row['id']);
            $this->db->query(
                "UPDATE ut_sm_page_subscriptions
                 SET page_name = '{$pageNameQ}',
                     page_access_token = '{$pageTokenQ}',
                     instagram_account_id = " . ($instagramAccountId === '' ? 'NULL' : "'{$igIdQ}'") . ",
                     date_modified = '{$nowQ}'
                 WHERE id = '{$idQ}'"
            );
            return;
        }

        $deletedCheck = $this->db->limitQuery(
            "SELECT id FROM ut_sm_page_subscriptions
             WHERE page_id = '{$pageIdQ}' AND account_type = '{$typeQ}'
             ORDER BY date_modified DESC",
            0,
            1
        );
        $deletedRow = $this->db->fetchByAssoc($deletedCheck);
        if (!empty($deletedRow['id'])) {
            $idQ = $this->db->quote($deletedRow['id']);
            $this->db->query(
                "UPDATE ut_sm_page_subscriptions
                 SET page_name = '{$pageNameQ}',
                     page_access_token = '{$pageTokenQ}',
                     instagram_account_id = " . ($instagramAccountId === '' ? 'NULL' : "'{$igIdQ}'") . ",
                     deleted = 0,
                     date_modified = '{$nowQ}'
                 WHERE id = '{$idQ}'"
            );
            return;
        }

        $id = create_guid();
        $idQ = $this->db->quote($id);
        $this->db->query(
            "INSERT INTO ut_sm_page_subscriptions
             (id, date_entered, date_modified, deleted, page_id, page_name, page_access_token,
              account_type, instagram_account_id, assignment_type)
             VALUES
             ('{$idQ}', '{$nowQ}', '{$nowQ}', 0, '{$pageIdQ}', '{$pageNameQ}', '{$pageTokenQ}',
              '{$typeQ}', " . ($instagramAccountId === '' ? 'NULL' : "'{$igIdQ}'") . ", 'keep_empty')"
        );
    }

    /**
     * Soft-delete accounts not returned by Meta (keys: pageId|accountType).
     *
     * @param array $activeKeys
     * @param string $now
     */
    protected function softDeleteMissingAccounts(array $activeKeys, $now)
    {
        $nowQ = $this->db->quote($now);
        if (empty($activeKeys)) {
            $this->db->query(
                "UPDATE ut_sm_page_subscriptions
                 SET deleted = 1, date_modified = '{$nowQ}'
                 WHERE deleted = 0"
            );
            return;
        }

        $res = $this->db->query("SELECT id, page_id, account_type FROM ut_sm_page_subscriptions WHERE deleted = 0");
        while ($row = $this->db->fetchByAssoc($res)) {
            $type = !empty($row['account_type']) ? $row['account_type'] : 'facebook';
            $key = $row['page_id'] . '|' . $type;
            if (!in_array($key, $activeKeys, true)) {
                $idQ = $this->db->quote($row['id']);
                $this->db->query(
                    "UPDATE ut_sm_page_subscriptions
                     SET deleted = 1, date_modified = '{$nowQ}'
                     WHERE id = '{$idQ}'"
                );
            }
        }
    }

    /**
     * @deprecated Use softDeleteMissingAccounts
     * @param array $activePageIds
     * @param string $now
     */
    protected function softDeleteMissingPages(array $activePageIds, $now)
    {
        $keys = array();
        foreach ($activePageIds as $pid) {
            $keys[] = $pid . '|facebook';
        }
        $this->softDeleteMissingAccounts($keys, $now);
    }
}
