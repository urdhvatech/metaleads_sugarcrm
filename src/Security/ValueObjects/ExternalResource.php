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

namespace Sugarcrm\Sugarcrm\Security\ValueObjects;

use Sugarcrm\Sugarcrm\Security\Dns\Resolver;
use Sugarcrm\Sugarcrm\Security\Dns\ResolverFactory;
use Sugarcrm\Sugarcrm\Security\HttpClient\ExternalResourceInterface;
use Sugarcrm\Sugarcrm\Security\HttpClient\Url;

final class ExternalResource implements ExternalResourceInterface
{
    /** @var string */
    private string $ip;

    /** @var string */
    private string $host;

    /** @var string */
    private string $convertedUrl;

    /**
     * @var string
     */
    private string $origUrl;

    private function __construct()
    {
    }

    /**
     * Named constructor
     * @param string $url
     * @param array $privateIps
     * @param Resolver|null $resolver
     * @return static
     */
    public static function fromString(string $url, array $privateIps = [], ?Resolver $resolver = null): self
    {
        if (false === filter_var($url, FILTER_VALIDATE_URL)) {
            throw new \InvalidArgumentException('Invalid url was provided.');
        }

        $parts = parse_url($url);
        $host = $parts['host'] ?? null;
        $scheme = $parts['scheme'] ?? null;

        if (empty($host) || !in_array($scheme, ['http', 'https'], true)) {
            throw new \InvalidArgumentException('Invalid url was provided');
        }

        // If host is not IP then resolve it
        if (false === filter_var($host, FILTER_VALIDATE_IP)) {
            $ip = self::resolveToIp($host, $resolver);
        } else {
            $ip = $host;
        }

        if (self::isIpPrivate($ip, $privateIps)) {
            throw new \InvalidArgumentException('The target IP belongs to private network');
        }

        $urlValueObject = new self();
        $urlValueObject->ip = $ip;
        $urlValueObject->host = $host;
        $urlValueObject->convertedUrl = Url::build($parts, $ip);
        $urlValueObject->origUrl = $url;
        return $urlValueObject;
    }

    /**
     * Resolves hostname to the IP address using provided or default resolver
     * @param string $hostname Hostname
     * @param Resolver|null $resolver Resolver
     * @return string
     */
    private static function resolveToIp(string $hostname, ?Resolver $resolver): string
    {
        $resolver ??= ResolverFactory::getInstance();
        return $resolver->resolveToIp($hostname);
    }

    /**
     * Checks whether the provided IP address belongs to the private network
     * @param string $ip
     * @param array $privateIps
     * @return bool
     */
    private static function isIpPrivate(string $ip, array $privateIps): bool
    {
        if (empty($privateIps)) {
            return false;
        }

        $longIp = ip2long($ip);

        if ($longIp !== -1) {
            foreach ($privateIps as $privateIp) {
                [$start, $end] = explode('|', $privateIp);

                if ($longIp >= ip2long($start) && $longIp <= ip2long($end)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Resolves a relative URL according to RFC 2396 section 5.2
     * @param string $url
     * @param string|null $base
     * @return string
     */
    public function resolveLocation(string $url, ?string $base = null): string
    {
        $base ??= $this->origUrl;
        return Url::resolve($url, $base);
    }

    /**
     * @return string
     */
    public function getHost(): string
    {
        return $this->host;
    }

    /**
     * @return string
     */
    public function getConvertedUrl(): string
    {
        return $this->convertedUrl;
    }

    /**
     * @return string
     */
    public function getIp(): string
    {
        return $this->ip;
    }

    public function getOrigUrl(): string
    {
        return $this->origUrl;
    }
}
