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

class SecureSmarty implements Feature
{
    public static function getName(): string
    {
        return 'enforceSecureSmarty';
    }

    public static function getDescription(): string
    {
        return <<<'TEXT'
            Enforces usage of \Sugarcrm\Sugarcrm\Security\MLP\Alternatives\Sugar_Smarty instead of Smarty and Sugar_Smarty. 
            \Sugarcrm\Sugarcrm\Security\MLP\Alternatives\Sugar_Smarty enforces usage of secure Smarty settings and prevents
             dangerous features like 'eval', 'fetch', 'include_php', 'math', 'php', and 'template_object'.
            TEXT;
    }

    public static function isEnabledIn(string $version): bool
    {
        return version_compare($version, '14.2.0', '>=');
    }

    public static function isToggleableIn(string $version): bool
    {
        return version_compare($version, '25.2.0', '<');
    }
}
