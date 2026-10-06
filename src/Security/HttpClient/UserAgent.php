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

namespace Sugarcrm\Sugarcrm\Security\HttpClient;

use GuzzleHttp\Utils as GuzzleUtils;

class UserAgent
{
    private string $agent;
    private const CLIENT_VERSION = '1.0';

    public function __construct(string $agent)
    {
        $this->agent = $agent;
    }

    public static function forCurl(): self
    {
        return new self(sprintf('SugarCRM/%s/CURLClient/%s', self::getSugarVersion(), self::CLIENT_VERSION));
    }

    public static function forSoap(): self
    {
        return new self(sprintf('SugarCRM/%s/SOAPClient/%s', self::getSugarVersion(), self::CLIENT_VERSION));
    }

    public static function forErc(): self
    {
        return new self(ExternalResourceClient::defaultUserAgent());
    }

    public static function forGuzzle(): self
    {
        return new self(GuzzleUtils::defaultUserAgent());
    }

    public static function forGeneric(): self
    {
        return new self(sprintf('SugarCRM/%s', self::getSugarVersion()));
    }

    public function __toString(): string
    {
        return $this->agent;
    }

    private static function getSugarVersion(): string
    {
        return $GLOBALS['sugar_version'] ?: 'unknown_version';
    }
}
