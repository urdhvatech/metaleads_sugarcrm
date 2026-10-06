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

namespace Sugarcrm\Sugarcrm\Security\Dns;

final class ConfigResolver implements Resolver
{
    public function __construct(private readonly array $config)
    {
    }

    public function resolveToIp(string $hostname): string
    {
        if (!isset($this->config[$hostname])) {
            throw new QueryFailedException("Can't resolve $hostname to IP");
        }
        return $this->config[$hostname];
    }
}
