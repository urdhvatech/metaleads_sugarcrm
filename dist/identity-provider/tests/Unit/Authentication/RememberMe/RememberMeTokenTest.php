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

namespace Sugarcrm\IdentityProvider\Tests\Unit\Authentication\RememberMe;

use Sugarcrm\IdentityProvider\Authentication\RememberMe\RememberMeToken;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\User\UserInterface;

#[\PHPUnit\Framework\Attributes\CoversClass(\Sugarcrm\IdentityProvider\Authentication\RememberMe\RememberMeToken::class)]
class RememberMeTokenTest extends \PHPUnit\Framework\TestCase
{
    /** @var UsernamePasswordToken */
    private $userToken;

    /** @var RememberMeToken */
    private $rememberMeToken;

    protected function setUp(): void
    {
        parent::setUp();
        $this->userToken = new UsernamePasswordToken(
            'username',
            'password',
            'provider',
            ['test1', 'test2']
        );
        $this->rememberMeToken = new RememberMeToken($this->userToken);
    }

    public function testGetSource(): void
    {
        $this->assertEquals($this->userToken, $this->rememberMeToken->getSource());
    }

    public function testToString(): void
    {
        $this->assertEquals((string)$this->userToken, (string)$this->rememberMeToken);
    }

    public function testGetRoleNames(): void
    {
        $this->assertCount(2, $this->rememberMeToken->getRoleNames());
        $this->assertEquals($this->userToken->getRoleNames(), $this->rememberMeToken->getRoleNames());
    }

    public function testGetCredentials(): void
    {
        $this->assertEquals('password', $this->rememberMeToken->getCredentials());
        $this->assertEquals($this->userToken->getCredentials(), $this->rememberMeToken->getCredentials());
    }

    public function testGetUser(): void
    {
        $this->userToken->setUser('user');
        $this->assertEquals('user', $this->rememberMeToken->getUser());
        $this->assertEquals($this->userToken->getUser(), $this->rememberMeToken->getUser());

        $this->rememberMeToken->setUser('user1');
        $this->assertEquals('user1', $this->userToken->getUser());
        $this->assertEquals($this->userToken->getUser(), $this->rememberMeToken->getUser());
    }

    public function testGetUsername(): void
    {
        $this->assertEquals('username', $this->rememberMeToken->getUserIdentifier());
        $this->assertEquals($this->userToken->getUserIdentifier(), $this->rememberMeToken->getUserIdentifier());
    }

    public function testIsAuthenticated(): void
    {
        $this->assertTrue($this->rememberMeToken->isAuthenticated());
        $this->assertEquals($this->userToken->isAuthenticated(), $this->rememberMeToken->isAuthenticated());

        $this->rememberMeToken->setAuthenticated(false);
        $this->assertFalse($this->userToken->isAuthenticated());
        $this->assertEquals($this->userToken->isAuthenticated(), $this->rememberMeToken->isAuthenticated());
    }

    public function testEraseCredentials(): void
    {
        $user = $this->createMock(UserInterface::class);
        $this->userToken->setUser($user);
        $user->expects($this->once())->method('eraseCredentials');
        $this->rememberMeToken->eraseCredentials();
    }

    public function testGetAttributes(): void
    {
        $attributes = ['a' => 'b'];
        $this->userToken->setAttributes($attributes);
        $this->assertEquals($attributes, $this->rememberMeToken->getAttributes());
        $this->assertEquals($this->userToken->getAttributes(), $this->rememberMeToken->getAttributes());

        $attributes = ['a' => 'b', 'c' => 'd'];
        $this->rememberMeToken->setAttributes($attributes);
        $this->assertEquals($attributes, $this->userToken->getAttributes());
        $this->assertEquals($this->userToken->getAttributes(), $this->rememberMeToken->getAttributes());
    }

    public function testGetAttribute(): void
    {
        $this->userToken->setAttribute('a', 'b');
        $this->assertEquals('b', $this->rememberMeToken->getAttribute('a'));
        $this->assertEquals($this->userToken->getAttribute('a'), $this->rememberMeToken->getAttribute('a'));

        $this->rememberMeToken->setAttribute('c', 'd');
        $this->assertEquals('d', $this->userToken->getAttribute('c'));
        $this->assertEquals($this->userToken->getAttribute('c'), $this->rememberMeToken->getAttribute('c'));
    }

    public function testHasAttribute(): void
    {
        $this->assertFalse($this->rememberMeToken->hasAttribute('a'));
        $this->assertEquals($this->userToken->hasAttribute('a'), $this->rememberMeToken->hasAttribute('a'));

        $this->rememberMeToken->setAttribute('a', 'b');
        $this->assertTrue($this->userToken->hasAttribute('a'));
        $this->assertEquals($this->userToken->hasAttribute('a'), $this->rememberMeToken->hasAttribute('a'));
    }

    public function testGetProviderKey(): void
    {
        $this->assertEquals('provider', $this->rememberMeToken->getProviderKey());
        $this->assertEquals($this->userToken->getFirewallName(), $this->rememberMeToken->getProviderKey());
    }

    public function testSetLoggedIn(): void
    {
        $this->rememberMeToken->setLoggedIn();
        $this->assertTrue($this->rememberMeToken->isLoggedIn());
    }

    public function testSetLoggedActive(): void
    {
        $this->rememberMeToken->setLoggedActive();
        $this->assertTrue($this->rememberMeToken->isActive());
        $this->assertTrue($this->rememberMeToken->isLoggedIn());
    }

    public function testSetLoggedInactive(): void
    {
        $this->rememberMeToken->setLoggedActive();
        $this->assertTrue($this->rememberMeToken->isActive());

        $this->rememberMeToken->setLoggedInactive();
        $this->assertFalse($this->rememberMeToken->isActive());
        $this->assertTrue($this->rememberMeToken->isLoggedIn());
    }

    public function testSetLoggedOut(): void
    {
        $this->rememberMeToken->setLoggedActive();
        $this->assertTrue($this->rememberMeToken->isActive());
        $this->assertTrue($this->rememberMeToken->isLoggedIn());

        $this->rememberMeToken->setLoggedOut();
        $this->assertFalse($this->rememberMeToken->isActive());
        $this->assertFalse($this->rememberMeToken->isLoggedIn());
        $this->assertTrue($this->rememberMeToken->isLoggedOut());
    }

    public function testGetSRN(): void
    {
        $srn = 'srn:user';
        $this->userToken->setAttribute('srn', $srn);
        $this->assertEquals($srn, $this->rememberMeToken->getSRN());

        $srn = 'srn:user1';
        $this->rememberMeToken->setAttribute('srn', $srn);
        $this->assertEquals($srn, $this->rememberMeToken->getSRN());
    }

    public function testSerializeUnserialize(): void
    {
        $serialized = $this->rememberMeToken->serialize();
        
        $unserializedToken = unserialize($serialized);

        $this->assertEquals(
            $this->rememberMeToken->getUserIdentifier(),
            $unserializedToken->getUserIdentifier()
        );
    }
}
