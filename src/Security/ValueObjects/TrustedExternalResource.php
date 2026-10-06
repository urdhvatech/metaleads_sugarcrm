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

use Sugarcrm\Sugarcrm\Security\HttpClient\ExternalResourceInterface;

final class TrustedExternalResource implements ExternalResourceInterface
{
    /**
     * @var string
     */
    private string $origUrl;

    private static ExternalResource $externalResource;

    /**
     * @return string
     */
    public function getIp(): string
    {
        return self::$externalResource->getIp();
    }

    /**
     * @return string
     */
    public function getHost(): string
    {
        return self::$externalResource->getHost();
    }

    /**
     * @return string
     */
    public function getOrigUrl(): string
    {
        return $this->origUrl;
    }

    /**
     * @return string
     */
    public function getConvertedUrl(): string
    {
        return $this->origUrl;
    }


    /**
     * @param string $url
     * @return static
     */
    public static function fromString(string $url): self
    {
        self::$externalResource = ExternalResource::fromString($url);
        $trustedResource = new self();
        $trustedResource->origUrl = $url;
        return $trustedResource;
    }

    /**
     * Named constructor
     * @param ExternalResource $externalResource
     * @return static
     */
    public static function fromExternalResource(ExternalResource $externalResource): self
    {
        self::$externalResource = $externalResource;
        $trustedResource = new self();
        $trustedResource->origUrl = $externalResource->getOrigUrl();
        return $trustedResource;
    }

    /**
     * Resolves a relative URL according to RFC 2396 section 5.2
     * @param string $url
     * @return string
     */
    public function resolveLocation(string $url): string
    {
        return self::$externalResource->resolveLocation($url);
    }
}
