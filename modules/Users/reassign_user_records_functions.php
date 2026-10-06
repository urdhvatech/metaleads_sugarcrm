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

use Sugarcrm\Sugarcrm\Security\Escaper\Escape;

function getModuleMultiSelectOptions()
{
    global $beanList, $beanFiles, $dictionary, $app_list_strings;

    $exclude_modules = [
        'ImportMap',
        'UsersLastImport',
        'SavedSearch',
        'UserPreference',
        'SugarFavorites',
        'OAuthKey',
        'OAuthToken',
    ];

    if (!isset($_SESSION['reassignRecords']['assignedModuleListCache'])) {
        $beanListDup = $beanList;

        unset($beanListDup['ForecastManagerWorksheets']);

        foreach ($beanListDup as $m => $p) {
            if (empty($beanFiles[$p])) {
                unset($beanListDup[$m]);
                continue;
            }

            try {
                $obj = BeanFactory::newBean($m);

                if (empty($obj) || !is_object($obj)) {
                    unset($beanListDup[$m]);
                    continue;
                }

                if (
                    !isset($obj->field_defs['assigned_user_id']) || (
                        isset($obj->field_defs['assigned_user_id']['source']) &&
                        $obj->field_defs['assigned_user_id']['source'] === 'non-db'
                    ) || (
                        isset($dictionary[$obj->object_name]['reassignable']) &&
                        !isTruthy($dictionary[$obj->object_name]['reassignable'])
                    )
                ) {
                    unset($beanListDup[$m]);
                }
            } catch (Throwable $e) {
                $GLOBALS['log']->error('Exception creating bean for module: ' . $m . ' - ' . $e->getMessage());
                unset($beanListDup[$m]);
            }
        }

        $beanListDup = array_diff($beanListDup, $exclude_modules);

        $beanListDupDisp = [];
        foreach ($beanListDup as $m => $p) {
            $beanListDupDisp[$m] = $app_list_strings['moduleList'][$m] ?? $p;
        }

        asort($beanListDupDisp, SORT_STRING);

        $_SESSION['reassignRecords']['assignedModuleListCache'] = $beanListDup;
        $_SESSION['reassignRecords']['assignedModuleListCacheDisp'] = $beanListDupDisp;
    }

    $selected = [];

    if (!empty($_SESSION['reassignRecords']['modules']['list'])) {
        $selected = $_SESSION['reassignRecords']['modules']['list'];
        if (is_object($selected) && method_exists($selected, 'getArrayCopy')) {
            $selected = $selected->getArrayCopy();
        }
    } elseif (!empty($_SESSION['reassignRecords']['modules'])) {
        foreach ($_SESSION['reassignRecords']['modules'] as $key => $mod) {
            $selected[] = $key;
        }
    }

    $cacheDisp = $_SESSION['reassignRecords']['assignedModuleListCacheDisp'] ?? [];
    if (is_object($cacheDisp) && method_exists($cacheDisp, 'getArrayCopy')) {
        $cacheDisp = $cacheDisp->getArrayCopy();
    }

    return get_select_options_with_id_separate_key(
        $cacheDisp,
        $cacheDisp,
        $selected
    );
}

/**
 * Checks if a reassignment process is currently running and displays a warning if locked.
 * Automatically clears locks older than 30 minutes. Renders a warning message if lock is active.
 * @param string $nonce CSP nonce for inline JavaScript
 * @return bool True if locked and warning displayed, false if not locked
 */
function checkReassignmentProcessingLock(string $nonce): bool
{
    if (!empty($_SESSION['reassignRecords']['processing'])) {
        $processingStartTime = $_SESSION['reassignRecords']['processing_start_time'] ?? 0;
        $elapsedMinutes = round((time() - $processingStartTime) / 60, 1);

        if ((time() - $processingStartTime) > 1800) {
            unset($_SESSION['reassignRecords']['processing']);
            unset($_SESSION['reassignRecords']['processing_start_time']);
            return false;
        }

        renderProcessingLockWarning($elapsedMinutes, $nonce);
        return true;
    }

    return false;
}

/**
 * Renders a warning message when reassignment processing is locked.
 * Displays elapsed time and instructions for users waiting for the process to complete.
 * @param float $elapsedMinutes Time elapsed since processing started
 * @param string $nonce CSP nonce for inline event handlers
 * @return void
 */
function renderProcessingLockWarning(float $elapsedMinutes, string $nonce): void
{
    global $mod_strings;

    $elapsedMinutesRounded = round($elapsedMinutes, 1);
    $redirectUrl = "index.php?module=Users&action=reassignUserRecords";

    $lockMsg = sprintf($mod_strings['LBL_REASS_PROCESSING_LOCK_MSG'], $elapsedMinutesRounded);
    $lockMsg = Escape::html($lockMsg);
    $lockTitle = Escape::html($mod_strings['LBL_REASS_PROCESSING_LOCK_TITLE']);
    $lockWait = Escape::html($mod_strings['LBL_REASS_PROCESSING_LOCK_WAIT']);
    $lockError = Escape::html($mod_strings['LBL_REASS_PROCESSING_LOCK_ERROR']);
    $buttonGoBack = Escape::html($mod_strings['LBL_REASS_BUTTON_GO_BACK']);

    echo <<<HTML
        <div style="padding: 20px; margin: 20px 0; background: #fff3cd;
            border-left: 4px solid #ffc107; color: #856404;">
            <h3 style="margin-top: 0; color: #856404;">{$lockTitle}</h3>
            <p>{$lockMsg}</p>
            <p><strong>{$lockWait}</strong></p>
            <p>{$lockError}</p>
            <br>
            <input type="button" class="button" value="{$buttonGoBack}"
                   data-onclick-{$nonce}="document.location='{$redirectUrl}';">
        </div>
        HTML;
}

/**
 * Sets the reassignment processing lock in the session.
 * Prevents concurrent reassignment operations by the same user.
 * @return void
 */
function setReassignmentProcessingLock(): void
{
    $_SESSION['reassignRecords']['processing'] = true;
    $_SESSION['reassignRecords']['processing_start_time'] = time();
}

/**
 * Clears the reassignment processing lock from the session.
 * Should be called when reassignment process completes or is cancelled.
 * @return void
 */
function clearReassignmentProcessingLock(): void
{
    unset($_SESSION['reassignRecords']['processing']);
    unset($_SESSION['reassignRecords']['processing_start_time']);
}

/**
 * Displays the initial progress UI when reassignment starts.
 * Outputs HTML structure that will be updated via JavaScript as processing continues.
 * @return void
 */
function displayReassignmentProgressStart(): void
{
    global $mod_strings;

    $progressProcessing = Escape::html($mod_strings['LBL_REASS_PROGRESS_PROCESSING']);
    $progressStarting = Escape::html($mod_strings['LBL_REASS_PROGRESS_STARTING']);

    echo <<<HTML
        <div id="progress-status"
            style="padding: 10px; margin: 10px 0; background: #f0f0f0;
            border-left: 4px solid #0070d2; color: #333;">
            <strong style="color: #333;">{$progressProcessing}</strong><br>
            <span id="progress-text" style="color: #333;">{$progressStarting}</span>
        </div>
        <div id="progress-details" style="margin: 10px 0; color: #333;"></div>
        HTML;

    if (ob_get_level()) {
        ob_flush();
    }
    flush();
}

/**
 * Updates the progress display during reassignment processing.
 * Outputs JavaScript to update progress text with current statistics. Flushes output buffer
 * to provide real-time feedback to the user.
 * @param int $processedCount Number of records processed so far
 * @param int $totalRecords Total number of records to process
 * @param int $successCount Number of successfully reassigned records
 * @param int $failCount Number of failed reassignments
 * @param float $elapsedTime Time elapsed in seconds
 * @return void
 */
function displayReassignmentProgressUpdate(
    int $processedCount,
    int $totalRecords,
    int $successCount,
    int $failCount,
    float $elapsedTime
): void {
    global $mod_strings;

    $percentage = $totalRecords > 0 ? round(($processedCount / $totalRecords) * 100) : 100;
    $nonce = \Sugarcrm\Sugarcrm\CSP\Nonce::create();

    $progressText = sprintf(
        $mod_strings['LBL_REASS_PROGRESS_STATUS'],
        $processedCount,
        $totalRecords,
        $percentage,
        $successCount,
        $failCount,
        $elapsedTime
    );
    $progressText = Escape::html($progressText);

    echo <<<HTML
        <script nonce="{$nonce}">
        document.getElementById("progress-text").innerHTML = "{$progressText}";
        </script>
        HTML;

    if (ob_get_level()) {
        ob_flush();
    }
    flush();
}

/**
 * Displays completion message when reassignment finishes.
 *
 * Updates the progress UI with final statistics and changes visual indicator to success state.
 *
 * @param int $processedCount Total number of records processed
 * @param int $totalRecords Total number of records that were to be processed
 * @param int $successCount Number of successfully reassigned records
 * @param int $failCount Number of failed reassignments
 * @return void
 */
function displayReassignmentProgressComplete(
    int $processedCount,
    int $totalRecords,
    int $successCount,
    int $failCount
): void {
    global $mod_strings;

    $nonce = \Sugarcrm\Sugarcrm\CSP\Nonce::create();

    $completeText = sprintf(
        $mod_strings['LBL_REASS_PROGRESS_COMPLETE'],
        $processedCount,
        $totalRecords,
        $successCount,
        $failCount
    );
    $completeText = Escape::html($completeText);

    echo <<<HTML
        <script nonce="{$nonce}">
        document.getElementById("progress-text").innerHTML = "{$completeText}";
        document.getElementById("progress-status").style.borderLeftColor = "#28a745";
        </script>
        HTML;

    if (ob_get_level()) {
        ob_flush();
    }
    flush();
}

function processConditions(SugarBean $bean, string $fromuser, array $moduleFilters, string $module, array $data): array
{
    $db = $bean->db;
    $table = $bean->table_name;
    $q_tables = " {$table} ";
    $q_where = "WHERE {$table}.deleted=0 AND {$table}.assigned_user_id = " . $db->quoted($fromuser);

    if (isset($moduleFilters[$module]['fields']) && is_array($moduleFilters[$module]['fields'])) {
        $custom_added = false;
        foreach ($moduleFilters[$module]['fields'] as $meta) {
            $metaName = $meta['name'];

            if (!empty($data[$metaName])) {
                $_SESSION['reassignRecords']['filters'][$metaName] = $data[$metaName];
            }

            $is_custom = !empty($meta['custom_table']);

            if ($is_custom && !$custom_added) {
                $q_tables .= "INNER JOIN {$table}_cstm ON {$table}.id = {$table}_cstm.id_c ";
                $custom_added = true;
            }

            $addcstm = $is_custom ? '_cstm' : '';

            switch ($meta['type']) {
                case 'text':
                case 'select':
                    $q_where .= sprintf(
                        ' and %s.%s = %s ',
                        $table . $addcstm,
                        $meta['dbname'],
                        $db->quoted($data[$metaName])
                    );

                    break;
                case 'multiselect':
                    if (empty($data[$metaName])) {
                        continue 2;
                    }

                    if (
                        safeCount($data[$metaName]) == 1 &&
                        empty($data[$metaName][0]) &&
                        $data[$metaName][0] !== '0'
                    ) {
                        continue 2;
                    }

                    $empty_check = '';
                    foreach ($data[$metaName] as $onevalue) {
                        if (empty($onevalue)) {
                            $empty_check .= " OR {$table}{$addcstm}.{$meta['dbname']} IS NULL ";
                        }
                    }

                    $in_string = implode(',', array_map(function ($value) use ($db): string {
                        return $db->quoted($value);
                    }, (array)$data[$metaName]));

                    $q_where .= " AND ({$table}{$addcstm}.{$meta['dbname']} IN ($in_string) $empty_check)";
                    break;
                default:
                    continue 2;
                    break;
            }
        }
    }
    return [$q_tables, $q_where];
}
