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

namespace Sugarcrm\IdentityProvider\Tests\Unit\Authentication\Provider\SAML;

use Sugarcrm\IdentityProvider\Authentication\Provider\SAML\AuthServiceProvider;
use Sugarcrm\IdentityProvider\Authentication\Token\SAML\ActionTokenInterface;
use Sugarcrm\IdentityProvider\Saml2\AuthPostBinding;
use Sugarcrm\IdentityProvider\Saml2\AuthRedirectBinding;
use Sugarcrm\IdentityProvider\Tests\IDMFixturesHelper;

#[\PHPUnit\Framework\Attributes\CoversClass(Sugarcrm\IdentityProvider\Authentication\Provider\SAML\AuthServiceProvider::class)]
class AuthServiceProviderTest extends \PHPUnit\Framework\TestCase
{
    public function testWrongConfig()
    {
        $this->expectException(\InvalidArgumentException::class);
        $authServiceProvider = new AuthServiceProvider([]);
    }

    public static function getAuthServiceDataProvider()
    {
        $baseConfig = IDMFixturesHelper::getOktaParameters();
        return [
            'validConfigWithLoginAction' => [
                'config' => $baseConfig,
                'tokenAction' => ActionTokenInterface::LOGIN_ACTION,
                'expectedInstance' => AuthRedirectBinding::class,
            ],
            'validConfigWithLogoutAction' => [
                'config' => $baseConfig,
                'tokenAction' => ActionTokenInterface::LOGOUT_ACTION,
                'expectedInstance' => AuthPostBinding::class,
            ],
        ];
    }

    /**
     * Checks getAuthService logic.
     *
     * @param $config
     * @param $tokenAction
     * @param $expectedInstance
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('getAuthServiceDataProvider')]
    public function testGetAuthService($config, $tokenAction, $expectedInstance)
    {
        $authServiceProvider = new AuthServiceProvider($config);

        $token = $this->createMock(ActionTokenInterface::class);
        $token->expects($this->any())
            ->method('getAction')
            ->willReturn($tokenAction);

        $authService = $authServiceProvider->getAuthService($token);
        $this->assertInstanceOf($expectedInstance, $authService);
    }

    /**
     * Checks getAuthService logic when it executed several times.
     */
    public function testGetAuthServiceSeveralTimes()
    {
        $authServiceProvider = $this->getMockBuilder(AuthServiceProvider::class)
                                    ->setConstructorArgs([IDMFixturesHelper::getOktaParameters()])
                                    ->onlyMethods(['buildAuthService'])->getMock();
        $token = $this->createMock(ActionTokenInterface::class);
        $token->expects($this->exactly(3))
              ->method('getAction')
              ->willReturnOnConsecutiveCalls(
                  ActionTokenInterface::LOGIN_ACTION,
                  ActionTokenInterface::LOGOUT_ACTION,
                  ActionTokenInterface::LOGIN_ACTION
              );

        $authServicePostMock = $this->getMockBuilder(AuthPostBinding::class)->disableOriginalConstructor()->getMock();
        $authServiceRedirectMock = $this->getMockBuilder(AuthRedirectBinding::class)
                                        ->disableOriginalConstructor()
                                        ->getMock();
        $matcher = $this->exactly(2);

        $authServiceProvider->expects($matcher)
            ->method('buildAuthService')->willReturnCallback(function () use ($matcher) {
                return match ($matcher->numberOfInvocations()) {
                    1 => [ActionTokenInterface::LOGIN_ACTION],
                    2 => [ActionTokenInterface::LOGOUT_ACTION],
                };
            })->willReturnOnConsecutiveCalls($authServiceRedirectMock, $authServicePostMock);

        $authServiceOne = $authServiceProvider->getAuthService($token);
        $this->assertInstanceOf(AuthRedirectBinding::class, $authServiceOne);
        $authServiceTwo = $authServiceProvider->getAuthService($token);
        $this->assertInstanceOf(AuthPostBinding::class, $authServiceTwo);
        $authServiceOne = $authServiceProvider->getAuthService($token);
        $this->assertInstanceOf(AuthRedirectBinding::class, $authServiceOne);
    }

    public static function getAuthServiceInvalidConfigDataProvider()
    {
        $invalidLoginConfig = $invalidLogoutConfig =$baseConfig = IDMFixturesHelper::getOktaParameters();
        unset($invalidLoginConfig['idp']['singleSignOnService']);
        unset($invalidLogoutConfig['idp']['singleLogoutService']);
        return [
            'invalidConfig' => [
                'config' => [],
                'tokenAction' => ActionTokenInterface::LOGIN_ACTION,
            ],
            'invalidConfigWithLoginAction' => [
                'config' => $invalidLoginConfig,
                'tokenAction' => ActionTokenInterface::LOGIN_ACTION,
            ],
            'invalidConfigWithLogoutAction' => [
                'config' => $invalidLogoutConfig,
                'tokenAction' => ActionTokenInterface::LOGOUT_ACTION,
            ],
        ];
    }

    /**
     * Checks getAuthService logic when parameters are invalid.
     *
     * @param $config
     * @param $tokenAction
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('getAuthServiceInvalidConfigDataProvider')]
    public function testGetAuthServiceWithInvalidConfig($config, $tokenAction)
    {
        $this->expectException(\InvalidArgumentException::class);

        $authServiceProvider = new AuthServiceProvider($config);

        $token = $this->createMock(ActionTokenInterface::class);
        $token->expects($this->any())
              ->method('getAction')
              ->willReturn($tokenAction);

        $authServiceProvider->getAuthService($token);
    }
}
