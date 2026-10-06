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

namespace Sugarcrm\IdentityProvider\Tests\Unit\Authentication\Provider;

use Sugarcrm\IdentityProvider\Authentication\Provider\MixedAuthenticationProvider;
use Sugarcrm\IdentityProvider\Authentication\Provider\Providers;
use Sugarcrm\IdentityProvider\Authentication\Provider\LdapAuthenticationProvider;
use Sugarcrm\IdentityProvider\Authentication\Token\MixedUsernamePasswordToken;

use Symfony\Component\Security\Core\Authentication\Provider\DaoAuthenticationProvider;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Exception\AuthenticationException;

#[\PHPUnit\Framework\Attributes\CoversClass(\Sugarcrm\IdentityProvider\Authentication\Provider\MixedAuthenticationProvider::class)]
class MixedAuthenticationProviderTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var LdapAuthenticationProvider | \PHPUnit_Framework_MockObject_MockObject
     */
    protected $ldapProvider;

    /**
     * @var DaoAuthenticationProvider | \PHPUnit_Framework_MockObject_MockObject
     */
    protected $localProvider;

    /**
     * @var MixedAuthenticationProvider
     */
    protected $provider;

    /**
     * @var MixedUsernamePasswordToken
     */
    protected $mixedToken;

    /**
     * @var UsernamePasswordToken | \PHPUnit_Framework_MockObject_MockObject
     */
    protected $localToken;

    /**
     * @var UsernamePasswordToken | \PHPUnit_Framework_MockObject_MockObject
     */
    protected $ldapToken;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->ldapProvider = $this->createMock(LdapAuthenticationProvider::class);
        $this->localProvider = $this->createMock(DaoAuthenticationProvider::class);
        $this->provider = new MixedAuthenticationProvider(
            [$this->ldapProvider, $this->localProvider],
            Providers::PROVIDER_KEY_MIXED
        );

        $this->mixedToken = new MixedUsernamePasswordToken(
            'username',
            'password',
            Providers::PROVIDER_KEY_MIXED
        );

        $this->localToken = $this->createMock(UsernamePasswordToken::class);
        $this->ldapToken = $this->createMock(UsernamePasswordToken::class);
    }

    /**
     * Provides data for testSupports.
     *
     * @return array
     */
    public static function supportsProvider()
    {
        return [
            'supportedToken' => [
                'class' => MixedUsernamePasswordToken::class,
                'key' => Providers::PROVIDER_KEY_MIXED,
                'result' => true,
            ],
            'supportedClassUnsupportedKey' => [
                'class' => MixedUsernamePasswordToken::class,
                'key' => 'UnsupportedKey',
                'result' => false,
            ],
            'unsupportedClassSupportedKey' => [
                'class' => UsernamePasswordToken::class,
                'key' => Providers::PROVIDER_KEY_MIXED,
                'result' => false,
            ],
        ];
    }

    /**
     * @param string $class
     * @param string $key
     * @param bool $result
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('supportsProvider')]
    public function testSupports($class, $key, $result)
    {
        $token = new $class('username', 'password', $key);
        $this->assertEquals($result, $this->provider->supports($token));
    }

    public function testAuthenticateWithoutAnyAdditionalToken()
    {
        $this->expectException(\Symfony\Component\Security\Core\Exception\ProviderNotFoundException::class);

        $this->provider->authenticate($this->mixedToken);
    }

    public function testAuthenticateWithoutAnySuitableToken()
    {
        $this->expectException(\Symfony\Component\Security\Core\Exception\ProviderNotFoundException::class);

        $this->localToken->method('getFirewallName')->willReturn('providerKey');
        $this->mixedToken->setAttribute('mixedAuthTokens', [$this->localToken]);
        $this->provider->authenticate($this->mixedToken);
    }

    public function testSuccessAuthenticateThroughProvider()
    {
        $resultToken = $this->createMock(UsernamePasswordToken::class);
        $this->localToken->method('getFirewallName')
                         ->willReturn(Providers::PROVIDER_KEY_LOCAL);
        $this->localProvider->method('supports')->with($this->localToken)->willReturn(true);
        $this->localProvider->expects($this->once())
                                ->method('authenticate')
                            ->with($this->localToken)
                            ->willReturn($resultToken);

        $this->mixedToken->addToken($this->localToken);
        $this->assertEquals($resultToken, $this->provider->authenticate($this->mixedToken));
    }

    public function testFailedAuthenticateThroughProvider()
    {
        $this->expectException(\Symfony\Component\Security\Core\Exception\AuthenticationException::class);

        $this->localToken->method('getFirewallName')
                         ->willReturn(Providers::PROVIDER_KEY_LOCAL);
        $this->localProvider->method('supports')->with($this->localToken)->willReturn(true);
        $this->localProvider->expects($this->once())
                            ->method('authenticate')
                            ->with($this->localToken)
                            ->willThrowException(new AuthenticationException());

        $this->mixedToken->addToken($this->localToken);
        $this->provider->authenticate($this->mixedToken);
    }

    public function testSuccessAuthenticateThroughSeveralProviders()
    {
        $resultToken = $this->createMock(UsernamePasswordToken::class);

        $this->localToken->method('getFirewallName')
            ->willReturn(Providers::PROVIDER_KEY_LOCAL);
        $this->ldapToken->method('getFirewallName')
            ->willReturn(Providers::PROVIDER_KEY_LDAP);

        $this->localProvider->method('supports')->willReturnMap(
            [
                [$this->ldapToken, false],
                [$this->localToken, true],
            ]
        );
        $this->ldapProvider->method('supports')->willReturnMap(
            [
                [$this->ldapToken, true],
                [$this->localToken, false],
            ]
        );

        $this->ldapProvider->expects($this->once())
            ->method('authenticate')
            ->with($this->ldapToken)
            ->willThrowException(new AuthenticationException());

        $this->localProvider->expects($this->once())
            ->method('authenticate')
            ->with($this->localToken)
            ->willReturn($resultToken);

        $this->mixedToken->addToken($this->ldapToken);
        $this->mixedToken->addToken($this->localToken);
        $this->assertEquals($resultToken, $this->provider->authenticate($this->mixedToken));
    }

    public function testSuccessAuthenticateWithFirstTokenWin()
    {
        $this->localToken->method('getFirewallName')
            ->willReturn(Providers::PROVIDER_KEY_LOCAL);
        $this->ldapToken->method('getFirewallName')
            ->willReturn(Providers::PROVIDER_KEY_LDAP);

        $this->localProvider->method('supports')->willReturnMap(
            [
                [$this->ldapToken, false],
                [$this->localToken, true],
            ]
        );
        $this->ldapProvider->method('supports')->willReturnMap(
            [
                [$this->ldapToken, true],
                [$this->localToken, false],
            ]
        );

        $this->ldapProvider->method('authenticate')
            ->with($this->ldapToken)
            ->willReturn($this->ldapToken);

        $this->localProvider->method('authenticate')
            ->with($this->localToken)
            ->willReturn($this->localToken);

        $this->mixedToken->addToken($this->ldapToken);
        $this->mixedToken->addToken($this->localToken);
        $resultToken = $this->provider->authenticate($this->mixedToken);
        $this->assertSame($this->ldapToken, $resultToken);
    }
}
