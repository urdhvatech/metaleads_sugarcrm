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

use Microsoft\Kiota\Authentication\Oauth\BaseSecretContext;
use Microsoft\Kiota\Authentication\Oauth\DelegatedPermissionTrait;
use Microsoft\Kiota\Authentication\Oauth\TokenRequestContext;

/**
 * msgraph-sdk-php has problems with re-using access_token in requests
 * in that case we need to implement workaround with refresh_token
 */
class OnBehalfOfContextUsingRefreshToken extends BaseSecretContext implements TokenRequestContext
{
    use DelegatedPermissionTrait;

    private string $assertion;
    private array $additionalParams;

    public function __construct(string $tenantId, string $clientId, string $clientSecret, string $assertion, array $additionalParams = [])
    {
        if (!$assertion) {
            throw new \InvalidArgumentException("Assertion cannot be empty");
        }
        $this->assertion = $assertion;
        $this->additionalParams = $additionalParams;

        parent::__construct($tenantId, $clientId, $clientSecret);
    }

    public function getParams(): array
    {
        return array_merge($this->additionalParams, parent::getParams(), [
            'refresh_token' => $this->assertion,
            'grant_type' => $this->getGrantType(),
        ]);
    }

    public function getGrantType(): string
    {
        return 'refresh_token';
    }
}
