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

use Sugarcrm\IdentityProvider\Authentication\Exception\RuntimeException;
use Sugarcrm\IdentityProvider\Authentication\Provider\LdapAuthenticationProvider;
use Sugarcrm\IdentityProvider\Authentication\User;
use Sugarcrm\IdentityProvider\Authentication\UserProvider\LdapUserProvider;
use Sugarcrm\IdentityProvider\Authentication\UserMapping\LDAPUserMapping;
use Symfony\Component\Ldap\Adapter\QueryInterface;
use Symfony\Component\Ldap\Entry;
use Symfony\Component\Ldap\Exception\ConnectionException;
use Symfony\Component\Ldap\LdapInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Ldap\Adapter\CollectionInterface;
use Symfony\Component\Ldap\Exception\LdapException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;

/**
 * Class TenantConfigInitializerTest
 * @requires extension ldap
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\Sugarcrm\IdentityProvider\Authentication\Provider\LdapAuthenticationProvider::class)]
class LdapAuthenticationProviderTest extends \PHPUnit\Framework\TestCase
{

    /**
     * @var array
     */
    protected $ldapConfig;

    /**
     * @var \PHPUnit_Framework_MockObject_MockObject
     */
    protected $userProvider;

    /**
     * @var \PHPUnit_Framework_MockObject_MockObject
     */
    protected $ldap;

    /**
     * @var \PHPUnit_Framework_MockObject_MockObject
     */
    protected $ldapQuery;

    /**
     * @var \PHPUnit_Framework_MockObject_MockObject
     */
    protected $ldapCollection;

    /**
     * @var \PHPUnit_Framework_MockObject_MockObject
     */
    protected $userChecker;

    /**
     * @var \PHPUnit_Framework_MockObject_MockObject
     */
    protected $mapper;

    public function setUp(): void
    {
        $this->ldapConfig = [
            'baseDn' => 'dc=openldap,dc=com',
            'entryAttribute' => null,
            'groupMembership' => true,
            'groupDn' => 'cn=Administrators,ou=groups,dc=openldap,dc=com',
            'userUniqueAttribute' => null,
            'groupAttribute' => 'member',
            'includeUserDN' => false,
        ];
        $this->userProvider = $this->getMockBuilder(LdapUserProvider::class)
                                   ->disableOriginalConstructor()
                                   ->getMock();
        $this->ldap = $this->getMockBuilder(LdapInterface::class)->getMock();
        $this->ldapQuery = $this->getMockBuilder(QueryInterface::class)->getMock();
        $this->ldapCollection = $this->getMockBuilder(CollectionInterface::class)->getMock();
        $this->userChecker = $this->getMockBuilder(UserCheckerInterface::class)->getMock();
        $this->mapper = $this->getMockBuilder(LDAPUserMapping::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['map'])
            ->getMock();
        $this->mapper->method('map')->willReturn([]);

        parent::setUp();
    }

    public function testLdapEntryNotFound()
    {
        $this->expectException(RuntimeException::class);

        $userProvider = $this->getMockBuilder(UserProviderInterface::class)->getMock();
        $ldap = $this->getMockBuilder(LdapInterface::class)->getMock();
        $userChecker = $this->getMockBuilder(UserCheckerInterface::class)->getMock();

        $provider = $this->getMockBuilder(LdapAuthenticationProvider::class)
            ->setConstructorArgs([$userProvider, $userChecker, 'key', $ldap, $this->mapper, '', true])
            ->onlyMethods(['retrieveUser'])
            ->getMock()
        ;
        $provider->method('retrieveUser')->willReturn(new User('user1', 'pass', []));

        $token = new UsernamePasswordToken('user1', 'pass', 'key');
        $provider->authenticate($token);
    }

    public function testLdapEntryAttributeNotFound()
    {
        $this->expectException(RuntimeException::class);

        $userProvider = $this->getMockBuilder(UserProviderInterface::class)->getMock();
        $ldap = $this->getMockBuilder(LdapInterface::class)->getMock();
        $userChecker = $this->getMockBuilder(UserCheckerInterface::class)->getMock();

        $provider = $this->getMockBuilder(LdapAuthenticationProvider::class)
            ->setConstructorArgs([
                $userProvider,
                $userChecker,
                'key',
                $ldap,
                $this->mapper,
                '',
                true,
                ['entryAttribute' => 'attr1']
            ])
            ->onlyMethods(['retrieveUser'])
            ->getMock()
        ;
        $provider->method('retrieveUser')->willReturn(
            new User('user1', 'pass', ['entry' => new Entry('dn', [])])
        );

        $token = new UsernamePasswordToken('user1', 'pass', 'key');
        $provider->authenticate($token);
    }

    public function testEmptyPasswordShouldThrowAnException()
    {
        $this->expectException(BadCredentialsException::class);

        $userProvider = $this->getMockBuilder(UserProviderInterface::class)->getMock();
        $ldap = $this->getMockBuilder(LdapInterface::class)->getMock();
        $userChecker = $this->getMockBuilder(UserCheckerInterface::class)->getMock();

        $provider = $this->getMockBuilder(LdapAuthenticationProvider::class)
            ->setConstructorArgs([$userProvider, $userChecker, 'key', $ldap, $this->mapper, '', true])
            ->onlyMethods(['retrieveUser'])
            ->getMock()
        ;
        $provider->method('retrieveUser')->willReturn(
            new User('user1', '', ['entry' => new Entry('dn', [])])
        );

        $token = new UsernamePasswordToken('user1', '', 'key');
        $provider->authenticate($token);
    }

    public function testBindFailureShouldThrowAnException()
    {
        $this->expectException(BadCredentialsException::class);

        $userProvider = $this->getMockBuilder(UserProviderInterface::class)->getMock();
        $ldap = $this->getMockBuilder(LdapInterface::class)->getMock();
        $ldap
            ->expects($this->exactly(2))
            ->method('bind')
            ->will($this->throwException(new ConnectionException()))
        ;
        $userChecker = $this->getMockBuilder(UserCheckerInterface::class)->getMock();

        $provider = $this->getMockBuilder(LdapAuthenticationProvider::class)
            ->setConstructorArgs([$userProvider, $userChecker, 'key', $ldap, $this->mapper, '', true])
            ->onlyMethods(['retrieveUser'])
            ->getMock()
        ;
        $provider->method('retrieveUser')->willReturn(
            new User('user1', '', ['entry' => new Entry('dn', [])])
        );

        $token = new UsernamePasswordToken('user1', 'pass', 'key');
        $provider->authenticate($token);
    }

    public function testBindByEntryDNOnBindFailByBindAttr()
    {
        $userProvider = $this->getMockBuilder(UserProviderInterface::class)->getMock();
        $ldap = $this->getMockBuilder(LdapInterface::class)->getMock();
        $ldap
            ->expects($this->exactly(2))
            ->method('bind')
            ->willReturnOnConsecutiveCalls($this->throwException(new ConnectionException()), true)
        ;
        $userChecker = $this->getMockBuilder(UserCheckerInterface::class)->getMock();

        $provider = $this->getMockBuilder(LdapAuthenticationProvider::class)
            ->setConstructorArgs([$userProvider, $userChecker, 'key', $ldap, $this->mapper, '', true])
            ->onlyMethods(['retrieveUser'])
            ->getMock()
        ;
        $provider->method('retrieveUser')->willReturn(
            new User('user1', '', ['entry' => new Entry('dn', [])])
        );

        $token = new UsernamePasswordToken('user1', 'pass', 'key');
        $provider->authenticate($token);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('theDnForLdapBindDataProvider')]
    public function testDnForLdapBind($username, $dnString, $entryDn, $entryAttribute, $entryAttributeValue, $expected)
    {
        $password = 'pass';

        $userProvider = $this->getMockBuilder(UserProviderInterface::class)->getMock();
        $ldap = $this->getMockBuilder(LdapInterface::class)->getMock();
        $ldap->method('escape')->willReturnArgument(0);
        $ldap
            ->expects($this->once())
            ->method('bind')
            ->with($expected, $password)
        ;
        $userChecker = $this->getMockBuilder(UserCheckerInterface::class)->getMock();

        $provider = $this->getMockBuilder(LdapAuthenticationProvider::class)
            ->setConstructorArgs([
                    $userProvider,
                    $userChecker,
                    'key',
                    $ldap,
                    $this->mapper,
                    $dnString,
                    true,
                    ['entryAttribute' => $entryAttribute],
            ])
            ->onlyMethods(['retrieveUser'])
            ->getMock()
        ;
        $provider->method('retrieveUser')->willReturn(
            new User(
                $username,
                '',
                ['entry' => new Entry($entryDn, [strtolower($entryAttribute) => [$entryAttributeValue]])]
            )
        );

        $token = new UsernamePasswordToken($username, $password, 'key');
        $provider->authenticate($token);
    }

    public static function theDnForLdapBindDataProvider()
    {
        return [
            // have dnString, no entryAttribute - standard behaviour
            ['user1', '{username}', 'dn', '', '', 'dn'],
            // no dnString, no entryAttribute - get DN from Ldap Entry
            ['user1', '', 'dn', '', '', 'dn'],
            // have dnString, have entryAttribute - get entryAttribute value from Ldap Entry, use it as username
            ['user1', '{username}@test.com', 'dn', 'attr1', 'attr1value', 'attr1value@test.com'],
            // no dnString, have entryAttribute - get entryAttribute value from Ldap Entry, use it as username
            ['user1', '', 'dn', 'attr1', 'attr1value', 'attr1value'],
            // have entryAttribute in uppercase  - get entryAttribute value from Ldap Entry, use it as username
            ['user1', '', 'dn', 'ATTR1', 'attr1value', 'attr1value'],
        ];
    }

    public function testGroupCheckNoDN()
    {
        $this->expectException(\Symfony\Component\Security\Core\Exception\AuthenticationException::class);
        $this->expectExceptionMessage('LDAP config option groupDn must not be empty');

        $this->ldap->expects($this->once())
            ->method('bind')
            ->with('dn', $password = 'pass');
        $provider = $this->getMockBuilder(LdapAuthenticationProvider::class)
            ->setConstructorArgs([
                $this->userProvider,
                $this->userChecker,
                'key',
                $this->ldap,
                $this->mapper,
                '{username}',
                true,
                ['groupMembership' => true],
            ])
            ->onlyMethods(['retrieveUser'])
            ->getMock();
        $provider->expects($this->any())->method('retrieveUser')->willReturn(
            new User($username = 'user1', '', ['entry' => new Entry('dn')])
        );
        $token = new UsernamePasswordToken($username, $password, 'key');
        $provider->authenticate($token);
    }

    public function testGroupCheckNoGroupAttribute()
    {
        $this->expectException(\Symfony\Component\Security\Core\Exception\AuthenticationException::class);
        $this->expectExceptionMessage('LDAP groupAttribute must not be empty');

        $this->ldap->expects($this->once())
            ->method('bind')
            ->with('dn', $password = 'pass');
        $provider = $this->getMockBuilder(LdapAuthenticationProvider::class)
            ->setConstructorArgs([
                $this->userProvider,
                $this->userChecker,
                'key',
                $this->ldap,
                $this->mapper,
                '{username}',
                true,
                ['groupMembership' => true, 'groupDn' => 'cn=Administrators,ou=groups,dc=openldap,dc=com'],
            ])
            ->onlyMethods(['retrieveUser'])
            ->getMock();
        $provider->expects($this->any())->method('retrieveUser')->willReturn(
            new User($username = 'user1', '', ['entry' => new Entry('dn')])
        );
        $token = new UsernamePasswordToken($username, $password, 'key');
        $provider->authenticate($token);
    }

    public function testGroupCheckNoBaseDn()
    {
        $this->expectException(\Symfony\Component\Security\Core\Exception\AuthenticationException::class);
        $this->expectExceptionMessage('LDAP baseDn must not be empty');

        $this->ldap->expects($this->once())
            ->method('bind')
            ->with('dn', $password = 'pass');
        $provider = $this->getMockBuilder(LdapAuthenticationProvider::class)
            ->setConstructorArgs([
                $this->userProvider,
                $this->userChecker,
                'key',
                $this->ldap,
                $this->mapper,
                '{username}',
                true,
                [
                    'groupMembership' => true,
                    'groupDn' => 'cn=Administrators,ou=groups,dc=openldap,dc=com',
                    'groupAttribute' => 'member',
                ],
            ])
            ->onlyMethods(['retrieveUser'])
            ->getMock();
        $provider->expects($this->any())->method('retrieveUser')->willReturn(
            new User($username = 'user1', '', ['entry' => new Entry('dn')])
        );
        $token = new UsernamePasswordToken($username, $password, 'key');
        $provider->authenticate($token);
    }

    public function testGroupCheckNoUserUniqueAttributeNoEntity()
    {
        $this->expectException(\Symfony\Component\Security\Core\Exception\AuthenticationException::class);
        $this->expectExceptionMessage('LDAP user does not belong to group specified');

        $this->ldap->expects($this->once())
            ->method('bind')
            ->with('dn', $password = 'pass');
        $this->ldap->expects($this->once())
            ->method('query')
            ->with(
                $this->equalTo($this->ldapConfig['groupDn']),
                $this->equalTo('(' . $this->ldapConfig['groupAttribute'] . '=' . ($userDn = 'dn') . ')')
            )
            ->willReturn($this->ldapQuery);
        $this->ldap->expects($this->once())
            ->method('escape')
            ->willReturnArgument(0);
        $this->ldapQuery->expects($this->once())->method('execute')->willReturn($this->ldapCollection);
        $this->ldapCollection->expects($this->once())->method('count')->willReturn(0);

        $provider = $this->getMockBuilder(LdapAuthenticationProvider::class)
            ->setConstructorArgs([
                $this->userProvider,
                $this->userChecker,
                'key',
                $this->ldap,
                $this->mapper,
                '{username}',
                true,
                $this->ldapConfig,
            ])
            ->onlyMethods(['retrieveUser'])
            ->getMock();
        $provider->expects($this->any())->method('retrieveUser')->willReturn(
            new User($username = 'user1', '', ['entry' => new Entry($userDn)])
        );
        $token = new UsernamePasswordToken($username, $password, 'key');
        $provider->authenticate($token);
    }

    public function testGroupCheckUserUniqueAttributeNotFindGroup()
    {
        $this->expectException(\Symfony\Component\Security\Core\Exception\AuthenticationException::class);
        $this->expectExceptionMessageMatches('~^Could.*group.*$~');

        $this->ldap->expects($this->once())
            ->method('bind')
            ->with('dn', $password = 'pass');
        $this->ldap->expects($this->once())
            ->method('query')
            ->with(
                $this->equalTo($this->ldapConfig['groupDn']),
                $this->equalTo('(member=unique=test,dc=openldap,dc=com)')
            )
            ->willReturn($this->ldapQuery);
        $this->ldap->expects($this->once())
            ->method('escape')
            ->willReturnArgument(0);
        $this->ldapQuery
            ->expects($this->once())
            ->method('execute')
            ->willThrowException(new LdapException());

        $this->ldapCollection->expects($this->never())->method('count')->willReturn(0);

        $provider = $this->getMockBuilder(LdapAuthenticationProvider::class)
            ->setConstructorArgs([
                $this->userProvider,
                $this->userChecker,
                'key',
                $this->ldap,
                $this->mapper,
                '{username}',
                true,
                array_merge($this->ldapConfig, [
                    'userUniqueAttribute' => ($userUnique = 'unique'),
                    'includeUserDN' => true,
                ]),
            ])
            ->onlyMethods(['retrieveUser'])
            ->getMock();
        $provider->expects($this->any())->method('retrieveUser')->willReturn(
            new User($username = 'user1', '', ['entry' => new Entry('dn', [
                $userUnique => ['test'],
            ])])
        );
        $token = new UsernamePasswordToken($username, $password, 'key');
        $provider->authenticate($token);
    }

    /**
     * Test authentication when search option is empty
     * Trying to bind by user's username and password
     */
    public function testUserRetrievingWithEmptySearchDN()
    {
        $username = 'testuser';
        $password = 'testpassword';
        $provider = $this->getMockBuilder(LdapAuthenticationProvider::class)
            ->setConstructorArgs([
                $this->userProvider,
                $this->userChecker,
                'key',
                $this->ldap,
                $this->mapper,
                '{username}',
                true,
                $this->ldapConfig,
            ])
            ->onlyMethods(['groupCheck'])
            ->getMock();
        $token = new UsernamePasswordToken($username, $password, 'key');
        $user = new User($username, '', ['entry' => new Entry('dn', [])]);

        $this->userProvider->expects($this->once())
            ->method('loadUserByIdentifier')
            ->with($username)
            ->willThrowException(new UserNotFoundException());
        $this->userProvider->expects($this->once())
            ->method('loadUserByToken')->with($token)->willReturn($user);
        $provider->authenticate($token);
    }

    /**
     * Test authentication when search option is empty
     * Trying to bind by user's username and password
     */
    public function testUserRetrievingWithEmptySearchDNAndInvalidAdminCredentials()
    {
        $this->expectException(\Symfony\Component\Ldap\Exception\LdapException::class);

        $username = 'testuser';
        $password = 'testpassword';
        $provider = $this->getMockBuilder(LdapAuthenticationProvider::class)
            ->setConstructorArgs([
                $this->userProvider,
                $this->userChecker,
                'key',
                $this->ldap,
                $this->mapper,
                '{username}',
                true,
                array_merge($this->ldapConfig, ['searchDn' => 'admin', 'searchPassword' => 'admin']),
            ])
            ->onlyMethods(['groupCheck'])
            ->getMock();
        $token = new UsernamePasswordToken($username, $password, 'key');
        $ldapException = new LdapException('ldap exception', 0, new ConnectionException('Invalid credentials'));
        $this->userProvider->expects($this->once())
            ->method('loadUserByIdentifier')
            ->with($username)
            ->willThrowException($ldapException);
        $this->userProvider->expects($this->never())->method('loadUserByToken');
        $provider->authenticate($token);
    }

    public function testRetrieveUserReturnsUserWithIdentityPairAndMappedAttributes()
    {
        $mapper = $this->createMock(LDAPUserMapping::class);
        $username = 'testuser';
        $password = 'testpassword';
        $ldapEntry = new Entry('dn', []);
        $identityMap = [
            'field' => 'username',
            'value' => $username,
        ];
        $mappedAttributes = ['attributes' => ['attributes_given_name' => 'Baz'] ];
        $provider = $this->getMockBuilder(LdapAuthenticationProvider::class)
            ->setConstructorArgs([
                $this->userProvider,
                $this->userChecker,
                'key',
                $this->ldap,
                $mapper,
                '{username}',
                true,
                array_merge($this->ldapConfig, ['searchDn' => 'admin', 'searchPassword' => 'admin']),
            ])
            ->onlyMethods(['groupCheck'])
            ->getMock();
        $token = new UsernamePasswordToken($username, $password, 'key');

        $user = $this->createMock(User::class);
        $user->method('getAttribute')->with('entry')->willReturn($ldapEntry);
        $user->method('getRoles')->willReturn([]);

        $this->userProvider->expects($this->once())
            ->method('loadUserByIdentifier')
            ->with($username)
            ->willReturn($user);

        $mapper->method('mapIdentity')->willReturn($identityMap);
        $mapper->method('map')->willReturn($mappedAttributes);
        $matcher = $this->exactly(3);

        $user->expects($matcher)
            ->method('setAttribute')->willReturnCallback(function () use ($matcher, $username, $mappedAttributes) {
                return match ($matcher->numberOfInvocations()) {
                    1 => ['identityField', 'username'],
                    2 => ['identityValue', $username],
                    3 => ['attributes', $mappedAttributes['attributes']],
                };
            });

        $provider->authenticate($token);
    }
}
