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

namespace Sugarcrm\Sugarcrm\GAI\Client;

use Psr\Http\Message\ResponseInterface;

interface Client
{
    /**
     * GAI Api call for making requests to the GAI service.
     *
     * @param string $method HTTP Method
     * @param string $method HTTP Endpoint
     * @param array $options The data to send to gai api.
     *
     * @return void
     */
    public function call(string $method, string $endpoint, array $options): ResponseInterface;

    /**
     * GAI Api call for access token requests to the GAI service.
     *
     * @param string $method HTTP Method
     * @param string $method HTTP Endpoint
     * @param array $options The data to send to gai api.
     * @param string $type The type of request (query or body)
     * @param array $headers The headers to send to gai api.
     *
     * @return void
     */
    public function callAccesToken(string $method, string $endpoint, array $options, string $type, array $headers): ResponseInterface;
}
