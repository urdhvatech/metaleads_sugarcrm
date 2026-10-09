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

if (is_file('modules/AOW_WorkFlow/aow_utils.php')) {
    require_once 'modules/AOW_WorkFlow/aow_utils.php';
}

/**
 * config.name is varchar(32). A round-robin key built from the account id is longer than that,
 * so the cursor is stored under a 32-character hash of the logical key.
 *
 * @param string $id
 * @return string
 */
function utSmRoundRobinConfigName($id)
{
    return md5((string) $id);
}

if (!function_exists('getRoundRobinUser')) {
    /**
     * Next user id in a round-robin list. Used when SuiteCRM AOW helpers are absent.
     *
     * @param array $users
     * @param string $id
     * @return string
     */
    function getRoundRobinUser($users, $id)
    {
        $users = array_values($users);
        if (empty($users)) {
            return '';
        }

        $admin = BeanFactory::newBean('Administration');
        $admin->retrieveSettings('ut_sm_rr');
        $settingKey = 'ut_sm_rr_' . utSmRoundRobinConfigName($id);
        $last = isset($admin->settings[$settingKey]) ? (string) $admin->settings[$settingKey] : '';
        $index = array_search($last, $users, true);
        if ($index === false || $index >= (count($users) - 1)) {
            return $users[0];
        }

        return $users[$index + 1];
    }
}

if (!function_exists('setLastUser')) {
    /**
     * @param string $userId
     * @param string $id
     */
    function setLastUser($userId, $id)
    {
        $admin = BeanFactory::newBean('Administration');
        $admin->saveSetting('ut_sm_rr', utSmRoundRobinConfigName($id), (string) $userId);
    }
}

/**
 * Per-account lead assignment (keep empty, round robin, specific user, security group).
 *
 * Reuses SuiteCRM AOW round-robin helpers for user rotation.
 */
class UTSMAssignmentService
{
    const TYPE_KEEP_EMPTY = 'keep_empty';
    const TYPE_ROUND_ROBIN = 'round_robin';
    const TYPE_SPECIFIC_USER = 'specific_user';
    const TYPE_SECURITY_GROUP = 'security_group';

    /**
     * @param SugarBean $lead
     * @param array $account Row from ut_sm_page_subscriptions
     */
    public function applyToLead($lead, array $account)
    {
        $type = !empty($account['assignment_type']) ? $account['assignment_type'] : self::TYPE_KEEP_EMPTY;

        switch ($type) {
            case self::TYPE_SPECIFIC_USER:
                $userId = !empty($account['assignment_user_id']) ? $account['assignment_user_id'] : '';
                if ($userId !== '' && $this->isActiveUser($userId)) {
                    $lead->assigned_user_id = $userId;
                }
                break;

            case self::TYPE_ROUND_ROBIN:
                $users = $this->decodeUserIds(!empty($account['assignment_rr_user_ids']) ? $account['assignment_rr_user_ids'] : '');
                $users = $this->filterActiveUsers($users);
                if (!empty($users)) {
                    $rrKey = 'ut_sm_' . $account['id'];
                    $next = getRoundRobinUser($users, $rrKey);
                    if (!empty($next)) {
                        $lead->assigned_user_id = $next;
                        setLastUser($next, $rrKey);
                    }
                }
                break;

            case self::TYPE_SECURITY_GROUP:
                $groupId = !empty($account['assignment_group_id']) ? $account['assignment_group_id'] : '';
                $users = $this->getSecurityGroupUserIds($groupId);
                if (!empty($users)) {
                    $rrKey = 'ut_sm_sg_' . $account['id'];
                    $next = getRoundRobinUser($users, $rrKey);
                    if (!empty($next)) {
                        $lead->assigned_user_id = $next;
                        setLastUser($next, $rrKey);
                    }
                }
                break;

            case self::TYPE_KEEP_EMPTY:
            default:
                // Leave unassigned
                break;
        }
    }

    /**
     * Human-readable assignment summary for the Connected Accounts table.
     *
     * @param array $account
     * @return string
     */
    public function getDisplayLabel(array $account)
    {
        $type = !empty($account['assignment_type']) ? $account['assignment_type'] : self::TYPE_KEEP_EMPTY;

        switch ($type) {
            case self::TYPE_ROUND_ROBIN:
                return 'Round Robin';
            case self::TYPE_SPECIFIC_USER:
                $name = $this->getUserDisplayName(!empty($account['assignment_user_id']) ? $account['assignment_user_id'] : '');
                return $name !== '' ? $name : 'Specific User';
            case self::TYPE_SECURITY_GROUP:
                $name = $this->getGroupDisplayName(!empty($account['assignment_group_id']) ? $account['assignment_group_id'] : '');
                return $name !== '' ? $name : 'Security Group';
            case self::TYPE_KEEP_EMPTY:
            default:
                return 'Keep Empty';
        }
    }

    /**
     * @param string $json
     * @return array
     */
    public function decodeUserIds($json)
    {
        if ($json === '' || $json === null) {
            return array();
        }
        // fetchByAssoc HTML-encodes quotes; decode before json_decode
        $json = from_html((string) $json);
        $decoded = json_decode($json, true);
        if (!is_array($decoded)) {
            return array();
        }
        $ids = array();
        foreach ($decoded as $id) {
            $id = trim((string) $id);
            if ($id !== '') {
                $ids[] = $id;
            }
        }
        return array_values(array_unique($ids));
    }

    /**
     * @param array $ids
     * @return string
     */
    public function encodeUserIds(array $ids)
    {
        $clean = array();
        foreach ($ids as $id) {
            $id = trim((string) $id);
            if ($id !== '') {
                $clean[] = $id;
            }
        }
        return json_encode(array_values(array_unique($clean)));
    }

    /**
     * @param string $groupId
     * @return array Indexed list of user ids
     */
    public function getSecurityGroupUserIds($groupId)
    {
        if ($groupId === '' || !file_exists('modules/SecurityGroups/SecurityGroup.php')) {
            return array();
        }
        require_once 'modules/SecurityGroups/SecurityGroup.php';
        $group = BeanFactory::getBean('SecurityGroups', $groupId);
        if (empty($group) || empty($group->id)) {
            return array();
        }
        $members = $group->getMembers();
        $ids = array();
        foreach ($members as $userId => $row) {
            if ($this->isActiveUser($userId)) {
                $ids[] = $userId;
            }
        }
        return array_values($ids);
    }

    /**
     * @param array $userIds
     * @return array
     */
    protected function filterActiveUsers(array $userIds)
    {
        $active = array();
        foreach ($userIds as $id) {
            if ($this->isActiveUser($id)) {
                $active[] = $id;
            }
        }
        return $active;
    }

    /**
     * @param string $userId
     * @return bool
     */
    protected function isActiveUser($userId)
    {
        if ($userId === '') {
            return false;
        }
        $user = BeanFactory::getBean('Users', $userId);
        if (empty($user) || empty($user->id) || !empty($user->deleted)) {
            return false;
        }
        if (isset($user->status) && $user->status !== 'Active') {
            return false;
        }
        return true;
    }

    /**
     * @param string $userId
     * @return string
     */
    protected function getUserDisplayName($userId)
    {
        if ($userId === '') {
            return '';
        }
        $user = BeanFactory::getBean('Users', $userId);
        if (empty($user) || empty($user->id)) {
            return '';
        }
        $name = trim($user->full_name);
        if ($name === '') {
            $name = trim($user->first_name . ' ' . $user->last_name);
        }
        if ($name === '') {
            $name = $user->user_name;
        }
        return $name;
    }

    /**
     * @param string $groupId
     * @return string
     */
    protected function getGroupDisplayName($groupId)
    {
        if ($groupId === '' || !file_exists('modules/SecurityGroups/SecurityGroup.php')) {
            return '';
        }
        $group = BeanFactory::getBean('SecurityGroups', $groupId);
        if (empty($group) || empty($group->id)) {
            return '';
        }
        return !empty($group->name) ? $group->name : '';
    }
}
