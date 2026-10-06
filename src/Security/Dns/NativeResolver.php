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

final class NativeResolver implements Resolver
{
    public function __construct(private readonly bool $preferIpv6 = false)
    {
    }

    public function resolveToIp(string $hostname): string
    {
        if ($hostname === 'localhost') {
            if ($this->preferIpv6) {
                return '[::1]';
            }

            return '127.0.0.1';
        }

        if ($this->preferIpv6) {
            $dns6 = @dns_get_record($hostname, DNS_AAAA) ?: [];
            $dns4 = @dns_get_record($hostname, DNS_A) ?: [];
            $dns = array_merge($dns4, $dns6);
        } else {
            $dns = @dns_get_record($hostname, DNS_A) ?: [];
        }
        $ip6 = [];
        $ip4 = [];
        foreach ($dns as $record) {
            if ($record["type"] === "A") {
                $ip4[] = $record["ip"];
            }
            if ($record["type"] === "AAAA") {
                $ip6[] = '[' . $record["ipv6"] . ']';
            }
        }
        // Fallback to gethostbynamel if dns_get_record failed
        if (empty($ip4) && empty($ip6)) {
            $ip4 = @gethostbynamel($hostname) ?: [];
        }
        if ($this->preferIpv6) {
            $result = count($ip6) < 1 ? (count($ip4) < 1 ? false : $ip4) : $ip6;
        } else {
            $result = count($ip4) < 1 ? false : $ip4;
        }
        if ($result === false) {
            throw new QueryFailedException("Can't resolve $hostname to IP");
        }
        return $result[0];
    }
}
