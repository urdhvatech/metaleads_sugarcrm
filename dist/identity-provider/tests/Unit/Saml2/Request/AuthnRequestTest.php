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

namespace Sugarcrm\IdentityProvider\Tests\Unit\Saml2\Request;

use OneLogin\Saml2\Settings;
use Sugarcrm\IdentityProvider\Saml2\Request\AuthnRequest;
use Sugarcrm\IdentityProvider\CSPRNG\GeneratorInterface;
use Sugarcrm\IdentityProvider\Tests\IDMFixturesHelper;

/**
 * Class AuthnRequestTest
 * @package Sugarcrm\IdentityProvider\Tests\Unit\Saml2\Request
 */
#[\PHPUnit\Framework\Attributes\CoversClass(Sugarcrm\IdentityProvider\Saml2\Request\AuthnRequest::class)]
class AuthnRequestTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var string
     */
    protected $requestId = 'someRequestId';

    /**
     * @var Settings|\PHPUnit_Framework_MockObject_MockObject
     */
    protected $settings = null;

    /**
     * @var GeneratorInterface|\PHPUnit_Framework_MockObject_MockObject
     */
    protected $generator = null;

    public function testRequestCreation()
    {
        $request = new AuthnRequest($this->settings, $this->generator);

        $Document = new \DOMDocument();
        $Document->loadXML($request->getXML());
        $this->assertEquals(
            $this->requestId,
            $Document->getElementsByTagName('AuthnRequest')->item(0)->attributes->getNamedItem('ID')->nodeValue
        );
    }

    public function testId()
    {
        $request = new AuthnRequest($this->settings, $this->generator);

        $this->assertEquals($this->requestId, $request->getId());
    }

    /**
     * Verify that we have proper encoding by the library
     */
    public function testUrlEncoding()
    {
        $request = new AuthnRequest($this->settings, $this->generator);

        $xml = $request->getXML();

        self::assertStringContainsString('http://sp/index.php?module=Users&amp;action=Authenticate', $xml);

        $document = new \DOMDocument();
        $document->loadXML($xml);

        $this->assertEquals(
            'http://sp/index.php?module=Users&action=Authenticate',
            $document->getElementsByTagName('AuthnRequest')->item(0)->attributes->getNamedItem('AssertionConsumerServiceURL')->nodeValue
        );
    }

    /**
     * @see testGetRequestCompressed
     * @return array
     */
    public static function argumentsForCompressedRequest()
    {
        return [
            'shouldCompress' => ['shouldCompress' => true, 'deflate' => null],
            'deflate' => ['shouldCompress' => false, 'deflate' => true],
            'both' => ['shouldCompress' => true, 'deflate' => true],
        ];
    }

    /**
     * @param bool $shouldCompress
     * @param bool|null $deflate
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('argumentsForCompressedRequest')]
    public function testGetRequestCompressed($shouldCompress, $deflate)
    {
        $this->settings->method('shouldCompressRequests')->willReturn($shouldCompress);

        $request = new AuthnRequest($this->settings, $this->generator);

        $encodedRequest = $request->getRequest($deflate);

        $this->assertEquals($request->getXML(), gzinflate(base64_decode($encodedRequest)));
    }

    /**
     * @see testGetRequestUnCompressed
     * @return array
     */
    public static function argumentsForUnCompressed()
    {
        return [
            'deflate' => ['shouldCompress' => true, 'deflate' => false],
            'shouldCompress' => ['shouldCompress' => false, 'deflate' => null],
            'both' => ['shouldCompress' => false, 'deflate' => false],
        ];
    }

    /**
     * @param bool $shouldCompress
     * @param bool|null $deflate
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('argumentsForUnCompressed')]
    public function testGetRequestUnCompressed($shouldCompress, $deflate)
    {
        $this->settings->method('shouldCompressRequests')->willReturn($shouldCompress);

        $request = new AuthnRequest($this->settings, $this->generator);

        $encodedRequest = $request->getRequest($deflate);

        $this->assertEquals($request->getXML(), base64_decode($encodedRequest));
    }

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->settings = $this->createMock(Settings::class);
        $this->settings->method('getSPData')
            ->willReturn(
                [
                    'entityId' => '',
                    'assertionConsumerService' => [
                        'url' => 'http://sp/index.php?module=Users&action=Authenticate',
                        'binding' => 'urn:oasis:names:tc:SAML:2.0:bindings:HTTP-POST',
                    ],
                    'NameIDFormat' => 'urn:oasis:names:tc:SAML:1.1:nameid-format:emailAddress',
                ]
            );
        $this->settings->method('getIdPData')
            ->willReturn(IDMFixturesHelper::getOktaParameters()['idp']);

        $this->generator = $this->createMock(GeneratorInterface::class);
        $this->generator
            ->method('generate')
            ->willReturn($this->requestId);
    }
}
