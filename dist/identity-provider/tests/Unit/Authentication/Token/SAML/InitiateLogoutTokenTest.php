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

namespace Sugarcrm\IdentityProvider\Tests\Unit\Authentication\Token\SAML;

use Sugarcrm\IdentityProvider\Authentication\Token\SAML\ActionTokenInterface;
use Sugarcrm\IdentityProvider\Authentication\Token\SAML\InitiateLogoutToken;

/**
 * Class SAMLInitiateTokenTest
 *
 * Class to test SAML Token.
 *
 * @package Sugarcrm\IdentityProvider\Tests\Unit\Token
 */
#[\PHPUnit\Framework\Attributes\CoversClass(Sugarcrm\IdentityProvider\Authentication\Token\SAML\InitiateLogoutToken::class)]
class InitiateLogoutTokenTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Test to check that InitiateLogoutToken has no credentials.
     */
    public function testNullCredentials()
    {
        $token = new InitiateLogoutToken();
        $this->assertNull($token->getCredentials());
    }

    /**
     * Tests token action.
     */
    public function testGetAction()
    {
        $token = new InitiateLogoutToken();
        $this->assertEquals(ActionTokenInterface::LOGOUT_ACTION, $token->getAction());
    }
}
