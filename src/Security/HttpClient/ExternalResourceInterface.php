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

namespace Sugarcrm\Sugarcrm\Security\HttpClient;

interface ExternalResourceInterface
{
    /**
     * @return string Returns external resource IP address
     */
    public function getIp(): string;

    /**
     * @return string Returns external resource hostname
     */
    public function getHost(): string;

    /**
     * @return string Returns original URL of the external resource
     */
    public function getOrigUrl(): string;

    /**
     * @return string Returns URL of external resource with the hostname translated to IP address if needed
     */
    public function getConvertedUrl(): string;

    /**
     * Named constructor
     * @param string $url
     * @return static
     */
    public static function fromString(string $url): self;

    /**
     * Resolves a relative URL according to RFC 2396 section 5.2
     * @param string $url
     * @return string
     */
    public function resolveLocation(string $url): string;
}
