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

use Sugarcrm\Sugarcrm\HistoricallyDelta\Decorators\FieldTypeDecorator;

use Sugarcrm\Sugarcrm\HistoricallyDelta\Decorators\Types\CurrencyFieldDecorator;
use Sugarcrm\Sugarcrm\HistoricallyDelta\Decorators\Types\DateFieldDecorator;
use Sugarcrm\Sugarcrm\HistoricallyDelta\Decorators\Types\DropdownFieldDecorator;
use Sugarcrm\Sugarcrm\HistoricallyDelta\Decorators\Types\DefaultFieldDecorator;

class FieldDecoratorFactory
{
    public const TYPE_CURRENCY = 'currency';
    public const TYPE_DATE = 'date';
    public const TYPE_ENUM = 'enum';

    /**
     * @var array<string, string>
     */
    private static array $customDecorators = [];

    /**
     * Decorator map for known field types.
     *
     * @var array<string, string>
     */
    private const DECORATOR_MAP = [
        self::TYPE_CURRENCY => CurrencyFieldDecorator::class,
        self::TYPE_DATE => DateFieldDecorator::class,
        self::TYPE_ENUM => DropdownFieldDecorator::class,
    ];

    /**
     * Register a custom field type decorator.
     *
     * @param string $type The field type to register the decorator for.
     * @param string $decoratorClass The class name of the decorator.
     *
     * @return void
     */
    public static function registerDecorator(string $type, string $decoratorClass): void
    {
        self::$customDecorators[$type] = $decoratorClass;
    }

    /**
     * Get the appropriate field type decorator based on the field definition.
     *
     * @param array $fieldDef The field definition array.
     *
     * @return FieldTypeDecorator The field type decorator instance.
     */
    public static function getDecorator(array $fieldDef): FieldTypeDecorator
    {
        $fieldType = $fieldDef['type'] ?? '';

        $decoratorClass = self::$customDecorators[$fieldType]
            ?? self::DECORATOR_MAP[$fieldType]
            ?? DefaultFieldDecorator::class;

        return new $decoratorClass($fieldDef);
    }
}
