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

declare(strict_types=1);

namespace Sugarcrm\Sugarcrm\FeatureToggle\Features;

use Sugarcrm\Sugarcrm\FeatureToggle\Feature;

class RectorModuleScanner implements Feature
{
    public static function getName(): string
    {
        return 'enableRectorModuleScanner';
    }

    public static function getDescription(): string
    {
        return <<<'TEXT'
            Enables checking of MLPs with Rector to ensure compatibility with newer PHP versions
            TEXT;
    }

    public static function isEnabledIn(string $version): bool
    {
        return version_compare($version, '14.2.0', '>=');
    }

    public static function isToggleableIn(string $version): bool
    {
        return version_compare($version, '14.1.0', '>=');
    }
}
