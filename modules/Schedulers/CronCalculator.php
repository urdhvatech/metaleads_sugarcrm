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
namespace Sugarcrm\Sugarcrm\modules\Schedulers;

use DateTime;

class CronCalculator
{
    /**
     * Calculate the next run time based on the cron expression.
     *
     * This method calculates the next run time for a scheduler based on its cron expression.
     * It handles various cron expressions and returns a DateTime object representing the next run time.
     *
     * @param array $cronParts Array containing the cron parts: [minute, hour, day of month, month, day of week]
     * @param DateTime $now Current time in UTC
     * @return DateTime|null The next run time or null if not found
     */
    public static function calculateNextRun(array $cronParts, DateTime $now): ?DateTime
    {
        // get next cron date '0::0::29::2::1' (2044-02-29 00:00:00) finds in 0.007 sec

        [$minExpr, $hourExpr, $domExpr, $monExpr, $dowExpr] = $cronParts;

        $validMinutes = self::getValidValues($minExpr, 0, 59);
        $validHours = self::getValidValues($hourExpr, 0, 23);
        $validDays = self::getValidValues($domExpr, 1, 31);
        $validMonths = self::getValidValues($monExpr, 1, 12);
        $validDaysOfWeek = self::getValidValues($dowExpr, 0, 6);

        // Start from the next minute
        $next = clone $now;
        $next->modify('+1 minute')->setTime((int)$next->format('H'), (int)$next->format('i'), 0);

        $maxAttempts = 1000;
        $attempts = 0;

        while ($attempts++ < $maxAttempts) {
            [$minute, $hour, $day, $month, $year, $dow] = self::getDateParts($next);

            if (!in_array($month, $validMonths, true)) {
                $next = self::adjustToNextValidMonth($next, $month, $validMonths, $year);
                continue;
            }

            if (!self::isCronDayValid($day, $dow, $domExpr, $dowExpr, $validDays, $validDaysOfWeek)) {
                $next->modify('+1 day')->setTime(0, 0, 0);
                continue;
            }

            if (!in_array($hour, $validHours, true)) {
                $next = self::adjustToNextValidHour($next, $hour, $validHours);
                continue;
            }

            if (!in_array($minute, $validMinutes, true)) {
                $next = self::adjustToNextValidMinute($next, $hour, $minute, $validMinutes);
                continue;
            }

            return $next;
        }

        return null;
    }

    /**
     * Get date parts from DateTime object.
     *
     * This function extracts the minute, hour, day, month, year, and day of week
     * from a DateTime object and returns them as an array.
     *
     * @param DateTime $dt The DateTime object to extract parts from.
     * @return array An array containing the minute, hour, day, month, year, and day of week.
     */
    public static function getDateParts(DateTime $dt): array
    {
        return [
            (int)$dt->format('i'), // minute
            (int)$dt->format('H'), // hour
            (int)$dt->format('d'), // day
            (int)$dt->format('m'), // month
            (int)$dt->format('Y'), // year
            (int)$dt->format('w'), // day of week
        ];
    }

    /**
     * Adjust the DateTime object to the next valid month based on the cron expression.
     *
     * This function finds the next valid month from the given month and adjusts the year if necessary.
     *
     * @param DateTime $dt The DateTime object to adjust.
     * @param int $month The current month.
     * @param array $validMonths Array of valid months from the cron expression.
     * @param int $year The current year.
     * @return DateTime The adjusted DateTime object with the next valid month.
     */
    public static function adjustToNextValidMonth(DateTime $dt, int $month, array $validMonths, int $year): DateTime
    {
        $nextMonth = self::findNextValue($month, $validMonths);
        if ($nextMonth <= $month) {
            $year++;
        }
        return new DateTime(sprintf('%d-%02d-01 00:00:00', $year, $nextMonth), $dt->getTimezone());
    }

    /** Check if the day and day of week are valid based on the cron expression.
     *
     * This function checks if the given day and day of week match the cron expression's
     * day of month and day of week parts, considering valid days and valid days of week.
     *
     * @param int $day The day of the month.
     * @param int $dow The day of the week (0 = Sunday, 6 = Saturday).
     * @param string $domExpr The cron expression part for day of month.
     * @param string $dowExpr The cron expression part for day of week.
     * @param array $validDays Array of valid days from the cron expression.
     * @param array $validDaysOfWeek Array of valid days of week from the cron expression.
     * @return bool True if both day and dow are valid, false otherwise.
     */
    public static function isCronDayValid(int $day, int $dow, string $domExpr, string $dowExpr, array $validDays, array $validDaysOfWeek): bool
    {
        if ($domExpr === '*' && $dowExpr === '*') {
            return true;
        }

        if ($domExpr !== '*' && $dowExpr !== '*') {
            return in_array($day, $validDays, true) || in_array($dow, $validDaysOfWeek, true);
        }

        if ($domExpr !== '*') {
            return in_array($day, $validDays, true);
        }

        return in_array($dow, $validDaysOfWeek, true);
    }

    /**
     * Adjust the DateTime object to the next valid hour based on the cron expression.
     *
     * This function finds the next valid hour from the given hour and adjusts the day if necessary.
     *
     * @param DateTime $dt The DateTime object to adjust.
     * @param int $hour The current hour.
     * @param array $validHours Array of valid hours from the cron expression.
     * @return DateTime The adjusted DateTime object with the next valid hour.
     */
    public static function adjustToNextValidHour(DateTime $dt, int $hour, array $validHours): DateTime
    {
        $nextHour = self::findNextValue($hour, $validHours);
        if ($nextHour <= $hour) {
            return $dt->modify('+1 day')->setTime(0, 0, 0);
        }
        return $dt->setTime($nextHour, 0, 0);
    }

    /**
     * Adjust the DateTime object to the next valid minute based on the cron expression.
     *
     * This function finds the next valid minute from the given hour and minute,
     * and adjusts the hour if necessary.
     *
     * @param DateTime $dt The DateTime object to adjust.
     * @param int $hour The current hour.
     * @param int $minute The current minute.
     * @param array $validMinutes Array of valid minutes from the cron expression.
     * @return DateTime The adjusted DateTime object with the next valid minute.
     */
    public static function adjustToNextValidMinute(DateTime $dt, int $hour, int $minute, array $validMinutes): DateTime
    {
        $nextMinute = self::findNextValue($minute, $validMinutes);
        if ($nextMinute <= $minute) {
            if ($hour == 23) {
                return $dt->modify('+1 day')->setTime(0, 0, 0);
            }
            return $dt->setTime($hour + 1, 0, 0);
        }
        return $dt->setTime($hour, $nextMinute, 0);
    }

    /**
     * Get valid values for a cron expression part.
     *
     * This method parses the cron expression part and returns an array of valid values.
     * It supports single values, ranges, lists, and step values.
     *
     * @param string $expr The cron expression part
     * @param int $min Minimum valid value
     * @param int $max Maximum valid value
     * @return array Array of valid values
     */
    public static function getValidValues(string $expr, int $min, int $max): array
    {
        if ($expr === '*') {
            return range($min, $max);
        }

        $validValues = [];
        $parts = explode(',', $expr);

        foreach ($parts as $part) {
            $part = trim($part);

            if (preg_match('/^(\*|\d+-\d+)\/(\d+)$/', $part, $matches)) {
                $rangePart = $matches[1];
                $step = (int)$matches[2];

                if ($rangePart === '*') {
                    for ($i = $min; $i <= $max; $i++) {
                        if (($i - $min) % $step === 0) {
                            $validValues[] = $i;
                        }
                    }
                } elseif (preg_match('/^(\d+)-(\d+)$/', $rangePart, $rangeMatches)) {
                    $start = (int)$rangeMatches[1];
                    $end = (int)$rangeMatches[2];
                    for ($i = $start; $i <= $end; $i++) {
                        if (($i - $start) % $step === 0) {
                            $validValues[] = $i;
                        }
                    }
                }
            } elseif (preg_match('/^(\d+)-(\d+)$/', $part, $matches)) {
                $start = max((int)$matches[1], $min);
                $end = min((int)$matches[2], $max);
                for ($i = $start; $i <= $end; $i++) {
                    $validValues[] = $i;
                }
            } elseif (is_numeric($part)) {
                $validValues[] = (int)$part;
            }
        }

        return array_unique($validValues);
    }

    /**
     * Find the next value greater than the current value in a sorted array.
     *
     * If no greater value is found, it returns the smallest value in the array.
     *
     * @param int $current The current value to compare against.
     * @param array $values The array of values to search through.
     * @return int The next greater value or the smallest value if none found.
     */
    public static function findNextValue(int $current, array $values): int
    {
        sort($values);
        foreach ($values as $val) {
            if ($val > $current) {
                return $val;
            }
        }
        return !empty($values) ? $values[0] : 0;
    }

    /**
     * Parses a date time string and returns it in 'Y-m-d H:i:s' format if valid.
     *
     * @param string $input The date time string to parse.
     * @return string|null The formatted date time string or null if parsing fails.
     */
    public static function parseAndFormatDateTime(string $input, string $inputFormat = 'Y-m-d H:i', string $outputFormat = 'Y-m-d H:i:s'): ?string
    {
        $dateTime = DateTime::createFromFormat($inputFormat, $input);
        if ($dateTime && $dateTime->format($inputFormat) === $input) {
            return $dateTime->format($outputFormat);
        }

        return null;
    }
}
