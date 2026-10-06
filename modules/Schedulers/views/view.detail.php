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


use Sugarcrm\Sugarcrm\modules\Schedulers\CronCalculator;

class SchedulersViewDetail extends ViewDetail
{
    /**
     * {@inheritDoc}
     *
     * @param bool $browserTitle Ignored
     */
    protected function _getModuleTitleListParam($browserTitle = false)
    {

        global $mod_strings;

        return "<a href='index.php?module=Schedulers&action=index'>" . $mod_strings['LBL_MODULE_TITLE'] . '</a>';
    }

    /**
     * display
     */
    public function display()
    {
        if (!empty($this->bean->system_job)) {
            ACLController::displayNoAccess(true);
            sugar_cleanup(true);
        }
        $this->bean->parseInterval();
        $this->bean->setIntervalHumanReadable();
        $this->assignUserTimezoneInfo();
        $this->ss->assign('JOB_INTERVAL', $this->bean->intervalHumanReadable);
        parent::display();
    }

    /**
     * @return void
     */
    public function assignUserTimezoneInfo(): void
    {
        global $timedate;
        $timezoneInfo = getUserTimezoneInfo();
        $nextRunDisplay = '';

        if (!empty($this->bean->job_interval) && $this->bean->status == 'Active') {
            try {
                $dateTimeFormat = $timedate->get_date_format(). ' ' . $timedate->get_time_format();
                $interval = $this->bean->job_interval;
                $cronParts = explode('::', $interval);
                if (count($cronParts) == 5) {
                    $systemUser = Scheduler::initUser();
                    $systemUserTz = $systemUser->getTimezone();
                    $userTz = new DateTimeZone($timezoneInfo['timezone']);

                    $now = new DateTime('now', $systemUserTz);
                    $utcTz = new DateTimeZone('UTC');
                    $start = !empty($this->bean->date_time_start)
                        ? (new DateTime($this->bean->date_time_start, $utcTz))->setTimezone($systemUserTz)
                        : $now;

                    $end = !empty($this->bean->date_time_end)
                        ? (new DateTime($this->bean->date_time_end, $utcTz))->setTimezone($systemUserTz)
                        : null;

                    $timeFrom = ($start > $now) ? $start : $now;
                    $nextRun = CronCalculator::calculateNextRun($cronParts, $timeFrom);

                    if ($nextRun && (!$end || $nextRun <= $end)) {
                        $nextRun->setTimezone($userTz);
                        $nextRuntime = $nextRun->format($dateTimeFormat) . ' ' . $timezoneInfo['timezone'];
                        $nextRunDisplay = sprintf(translate('LBL_NEXT_RUN'), $nextRuntime);
                    }
                }
            } catch (Exception $e) {
                LoggerManager::getLogger()->warn(
                    'Error calculating next run for scheduler: ' . $this->bean->name . '. Error: ' . $e->getMessage()
                );
            }
        }

        $this->ss->assign('next_run_display', $nextRunDisplay);
    }
}
