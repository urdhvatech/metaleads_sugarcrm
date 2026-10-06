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

namespace Sugarcrm\Sugarcrm\HistoricallyDelta\Decorators;

use Sugarcrm\Sugarcrm\HistoricallyDelta\Decorators\FieldDelta;

abstract class FieldTypeDecorator
{
    protected array $fieldDef;

    public function __construct(array $fieldDef)
    {
        $this->fieldDef = $fieldDef;
    }

    abstract public function decorate($past, $current): FieldDelta;

    protected function emptyResult(): FieldDelta
    {
        return new FieldDelta('', '', '', false);
    }

    /**
     * Builds the result based on the delta value.
     *
     * @param float|int $delta
     * @return array
     */
    protected function resolveStyle(float|int $delta): array
    {
        return match(true) {
            $delta > 0 => [$this->getPositiveSign(), $this->getPositiveClass()],
            $delta < 0 => [$this->getNegativeSign(), $this->getNegativeClass()],
            // @codingStandardsIgnoreLine Generic.WhiteSpace.ScopeIndent.IncorrectExact
            default => ['', ''],
        };
    }

    /**
     * Gets the CSS class for positive deltas.
     *
     * @return string
     */
    protected function getPositiveClass(): string
    {
        return 'text-emerald-500';
    }

    /**
     * Gets the CSS class for negative deltas.
     *
     * @return string
     */
    protected function getNegativeClass(): string
    {
        return 'text-red-500';
    }

    /**
     * Gets the sign for positive deltas.
     *
     * @return string
     */
    protected function getPositiveSign(): string
    {
        return '+';
    }

    /**
     * Gets the sign for negative deltas.
     *
     * @return string
     */
    protected function getNegativeSign(): string
    {
        return '-';
    }

    /**
     * Formats the delta value for display.
     *
     * @param string $text
     * @param float|int $delta
     * @param bool $shouldFormat
     *
     * @return FieldDelta
     */
    protected function formatDelta(string $text, float|int $delta, bool $shouldFormat = true): FieldDelta
    {
        [$sign, $css] = $this->resolveStyle($delta);

        return new FieldDelta($text, $sign, $css, $shouldFormat);
    }
}
