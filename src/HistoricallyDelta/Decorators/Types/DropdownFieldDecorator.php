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

use Sugarcrm\Sugarcrm\HistoricallyDelta\Decorators\FieldTypeDecorator;
use Sugarcrm\Sugarcrm\HistoricallyDelta\Decorators\FieldDelta;

class DropdownFieldDecorator extends FieldTypeDecorator
{
    protected array $dropdownOptions = [];

    /**
     * Constructor.
     *
     * @param array $fieldDef
     *
     * @return void
     */
    public function __construct(array $fieldDef)
    {
        $appListStrings = $this->getDropdownOptions();

        parent::__construct($fieldDef);

        $options = $fieldDef['options'] ?? [];

        if (is_string($options) && isset($appListStrings[$options])) {
            $options = $appListStrings[$options];
        }

        $this->dropdownOptions = is_array($options) ? array_keys($options) : [];
    }

    /**
     * Decorates the field delta based on the dropdown options.
     *
     * @param mixed $past
     * @param mixed $current
     *
     * @return FieldDelta
     */
    public function decorate($past, $current): FieldDelta
    {
        if (!$this->isValidDropdown() || !$this->isValidValues($past, $current)) {
            return $this->emptyResult();
        }

        $pastIndex = array_search($past, $this->dropdownOptions, true);
        $currentIndex = array_search($current, $this->dropdownOptions, true);

        if ($pastIndex === false || $currentIndex === false || $pastIndex === $currentIndex) {
            return $this->emptyResult();
        }

        $diff = $currentIndex - $pastIndex;

        return $this->buildResult($diff);
    }

    /**
     * Builds the result based on the delta value.
     *
     * @param int $diff
     *
     * @return FieldDelta
     */
    protected function buildResult(int $diff): FieldDelta
    {
        $absDiff = abs($diff);
        $label = $this->getUnitLabel($absDiff);
        $text = sprintf('%d %s', $absDiff, $label);

        return $this->formatDelta($text, $diff);
    }

    /**
     * Returns the unit label based on the count.
     * @param int $count
     *
     * @return string
     */
    protected function getUnitLabel(int $count): string
    {
        return $count === 1 ? translate('LBL_STEP') : translate('LBL_STEPS');
    }

    /**
     * Checks if the dropdown options are valid.
     *
     * @return bool
     */
    protected function isValidDropdown(): bool
    {
        return !empty($this->dropdownOptions);
    }

    /**
     * Checks if the past and current values are valid.
     *
     * @param mixed $past
     * @param mixed $current
     *
     * @return bool
     */
    protected function isValidValues($past, $current): bool
    {
        return $past !== null && $current !== null && $past !== '' && $current !== '';
    }

    /**
     * Retrieves the dropdown options from the app_list_strings.
     *
     * @return array
     */
    protected function getDropdownOptions(): array
    {
        global $app_list_strings;

        return is_array($app_list_strings) ? $app_list_strings : [];
    }
}
