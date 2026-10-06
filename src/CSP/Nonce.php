<?php

declare(strict_types=1);
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
namespace Sugarcrm\Sugarcrm\CSP;

final class Nonce
{
    private static string $nonce;

    /**
     * @throws \Random\RandomException
     */
    public static function create(): string
    {
        if (!empty(self::$nonce)) {
            return self::$nonce;
        }
        self::$nonce = bin2hex(random_bytes(16));
        return self::$nonce;
    }
}
