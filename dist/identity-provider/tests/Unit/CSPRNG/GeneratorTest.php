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

namespace Sugarcrm\IdentityProvider\Tests\Unit\CSPRNG;

use Sugarcrm\IdentityProvider\CSPRNG\Generator;
use Sugarcrm\IdentityProvider\CSPRNG\GeneratorInterface;

/**
 * Class GeneratorTest
 * @package Sugarcrm\IdentityProvider\Tests\Unit\CSPRNG
 */
#[\PHPUnit\Framework\Attributes\CoversClass(Sugarcrm\IdentityProvider\CSPRNG\Generator::class)]
class GeneratorTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var Generator
     */
    protected $generator;

    /**
     * Testing is generated string is unique.
     *
     * @param int $size
     * @param string $prefix
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('generatorProvider')]
    public function testRandom($size, $prefix)
    {
        $this->assertNotEquals($this->generator->generate($size, $prefix), $this->generator->generate($size, $prefix));
    }

    /**
     * @see testGenerateThrowsExceptions
     * @return array
     */
    public static function generateThrowsExceptionsProvider()
    {
        $prefixLong = 'test_sugar_';

        return [
            'PrefixLengthEqualsSize' => ['size' => strlen($prefixLong), 'prefix' => $prefixLong],
            'PrefixLengthGraterThenSize' => ['size' => strlen($prefixLong) - 1, 'prefix' => $prefixLong],
        ];
    }

    /**
     * Testing generator exceptions.
     *
     * @param int $size
     * @param string $prefix
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('generateThrowsExceptionsProvider')]
    public function testGenerateThrowsExceptions($size, $prefix)
    {
        $this->expectException(\RuntimeException::class);

        $this->generator->generate($size, $prefix);
    }
    
    /**
     * @see testGenerate
     * @return array
     */
    public static function generatorProvider()
    {
        return [
            'noPrefixSizeEven' => ['size' => 10, 'prefix' => ''],
            'noPrefixSizeOdd' => ['size' => 11, 'prefix' => ''],
            'noPrefixSizeEvenLong' => ['size' => 100, 'prefix' => ''],
            'noPrefixSizeOddLong' => ['size' => 101, 'prefix' => ''],
            
            'WithPrefixSizeEven' => ['size' => 10, 'prefix' => 'test_'],
            'WithPrefixSizeOdd' => ['size' => 11, 'prefix' => 'test_'],
            'WithPrefixSizeEvenLong' => ['size' => 100, 'prefix' => 'test_'],
            'WithPrefixSizeOddLong' => ['size' => 101, 'prefix' => 'test_'],
        ];
    }
    
    /**
     * Testing generator.
     *
     * @param int $size
     * @param string $prefix
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('generatorProvider')]
    public function testGenerate($size, $prefix)
    {
        $result = $this->generator->generate($size, $prefix);

        if ($prefix) {
            $this->assertStringStartsWith($prefix, $result);
        }

        $this->assertEquals($size, strlen($result), 'length of the random string invalid');
        self::assertStringNotContainsString('+', $result, 'contains invalid ID character');
        self::assertStringNotContainsString('/', $result, 'contains invalid ID character');
    }

    /**
     * Testing is class Generator implements interface GeneratorInterface
     */
    public function testInterface()
    {
        $this->assertInstanceOf(GeneratorInterface::class, $this->generator);
    }
    
    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        parent::setUp();
        
        $this->generator = new Generator();
    }
}
