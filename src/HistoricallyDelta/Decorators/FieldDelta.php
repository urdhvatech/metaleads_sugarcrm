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

/**
 * Data Transfer Object
 */
class FieldDelta implements \JsonSerializable
{
    /**
     * FieldDelta constructor
     *
     * @param string $delta
     * @param string $sign
     * @param string $cssClass
     * @param bool $shouldFormat
     *
     * @return void
     */
    public function __construct(
        // @codingStandardsIgnoreLine Generic.WhiteSpace.ScopeIndent.IncorrectExact
        public readonly string $delta,
        // @codingStandardsIgnoreLine Generic.WhiteSpace.ScopeIndent.IncorrectExact
        public readonly string $sign,
        // @codingStandardsIgnoreLine Generic.WhiteSpace.ScopeIndent.IncorrectExact
        public readonly string $cssClass,
        // @codingStandardsIgnoreLine Generic.WhiteSpace.ScopeIndent.IncorrectExact
        public readonly bool $shouldFormat
    ) {
        // Constructor body can be empty as properties are initialized directly
    }

    /**
     * Convert the object to an array
     *
     * @return array
     */
    public function toArray(): array
    {
        return [
            'delta' => $this->delta,
            'sign' => $this->sign,
            'cssClass' => $this->cssClass,
            'shouldFormat' => $this->shouldFormat,
        ];
    }

    /**
     * Convert the object to JSON
     *
     * @return array
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
