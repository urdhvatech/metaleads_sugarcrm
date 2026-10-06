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

namespace Sugarcrm\Sugarcrm\HistoricallyDelta\Decorators\Types;

use DateTime;
use Sugarcrm\Sugarcrm\HistoricallyDelta\Decorators\FieldTypeDecorator;
use Sugarcrm\Sugarcrm\HistoricallyDelta\Decorators\FieldDelta;

class DateFieldDecorator extends FieldTypeDecorator
{
    /**
     * Decorates the field delta based on the date difference.
     *
     * @param mixed $past
     * @param mixed $current
     *
     * @return FieldDelta
     */
    public function decorate($past, $current): FieldDelta
    {
        try {
            $tz = new \DateTimeZone('UTC');
            $pastDate = (new \DateTime($past))->setTimezone($tz)->setTime(0, 0, 0);
            $currentDate = (new \DateTime($current))->setTimezone($tz)->setTime(0, 0, 0);

            $interval = $pastDate->diff($currentDate);
            $isLater = $interval->invert === 0;

            // Prioritize the largest non-zero unit
            if ($interval->y > 0) {
                $value = $interval->y;
                $unitLabel = $value === 1 ? translate('LBL_YEAR') : translate('LBL_YEARS');
            } elseif ($interval->m > 0) {
                $value = $interval->m;
                $unitLabel = $value === 1 ? translate('LBL_MONTH') : translate('LBL_MONTHS');
            } elseif ($interval->d > 0) {
                $value = $interval->d;
                $unitLabel = $value === 1 ? translate('LBL_DAY') : translate('LBL_DAYS');
            } else {
                return $this->emptyResult();
            }

            $signedDelta = $isLater ? +$value : -$value;
            $direction = $isLater ? translate('LBL_LATER') : translate('LBL_SOONER');
            $text = "{$value} {$unitLabel} {$direction}";

            return $this->formatDelta($text, $signedDelta, false);
        } catch (\Exception $e) {
            return $this->emptyResult();
        }
    }

    /**
     * @inheritDoc
     */
    protected function getPositiveClass(): string
    {
        return 'price-down text-red-500';
    }

    /**
     * @inheritDoc
     */
    protected function getNegativeClass(): string
    {
        return 'price-up text-emerald-500';
    }

    /**
     * Gets the sign for positive deltas.
     *
     * @return string
     */
    protected function getPositiveSign(): string
    {
        return '';
    }

    /**
     * Gets the sign for negative deltas.
     *
     * @return string
     */
    protected function getNegativeSign(): string
    {
        return '';
    }
}
