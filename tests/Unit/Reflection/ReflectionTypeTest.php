<?php

namespace Tests\PhpUnitGen\Core\Unit\Reflection;

use Mockery;
use PhpUnitGen\Core\Reflection\ReflectionType;
use Roave\BetterReflection\Reflection\ReflectionType as BetterReflectionType;
use Tests\PhpUnitGen\Core\TestCase;

/**
 * Class ReflectionTypeTest.
 *
 * @covers \PhpUnitGen\Core\Reflection\ReflectionType
 */
class ReflectionTypeTest extends TestCase
{
    public function testMakeForBetterReflectionType(): void
    {
        $betterReflectionType = Mockery::mock(BetterReflectionType::class);
        $betterReflectionType->shouldReceive([
            '__toString' => '\\App\\User',
            'allowsNull' => false,
        ]);

        $reflectionType = ReflectionType::make($betterReflectionType, []);

        $this->assertFalse($reflectionType->isBuiltin());
        $this->assertFalse($reflectionType->isNullable());
        $this->assertSame('App\\User', $reflectionType->getType());
    }

    public function testMakeForBetterReflectionTypeAndDocumentor(): void
    {
        $betterReflectionType = Mockery::mock(BetterReflectionType::class);
        $betterReflectionType->shouldReceive([
            '__toString' => 'mixed',
            'allowsNull' => false,
        ]);

        $reflectionType = ReflectionType::make($betterReflectionType, [
            '\\App\\User',
            'null',
        ]);

        $this->assertFalse($reflectionType->isBuiltin());
        $this->assertTrue($reflectionType->isNullable());
        $this->assertSame('App\\User', $reflectionType->getType());
    }

    public function testMakeForPhpDocumentorTypesWithUnrealTypes(): void
    {
        $reflectionType = ReflectionType::make(null, [
            'null',
            'null',
        ]);

        $this->assertNull($reflectionType);
    }

    public function testMakeForPhpDocumentorTypesWitRealNullableType(): void
    {
        $reflectionType = ReflectionType::make(null, [
            'null',
            'null',
            '\\App\\User',
            'null',
            'int',
            'mixed',
            'null',
        ]);

        $this->assertFalse($reflectionType->isBuiltin());
        $this->assertTrue($reflectionType->isNullable());
        $this->assertSame('App\\User', $reflectionType->getType());
    }

    public function testMakeForPhpDocumentorTypesWitRealNotNullableArray(): void
    {
        $reflectionType = ReflectionType::make(null, [
            '\\App\\User[]',
        ]);

        $this->assertTrue($reflectionType->isBuiltin());
        $this->assertFalse($reflectionType->isNullable());
        $this->assertSame('array', $reflectionType->getType());
    }

    /**
     * @param bool   $expected
     * @param string $type
     *
     * @dataProvider isBuiltInDataProvider
     */
    public function testIsBuiltIn(bool $expected, string $type): void
    {
        $this->assertSame($expected, (new ReflectionType($type, false))->isBuiltIn());
    }

    public function isBuiltInDataProvider(): array
    {
        return [
            [true, 'int'],
            [true, 'float'],
            [true, 'string'],
            [true, 'bool'],
            [true, 'callable'],
            [true, 'self'],
            [true, 'parent'],
            [true, 'array'],
            [true, 'iterable'],
            [true, 'object'],
            [true, 'void'],
            [true, 'mixed'],
            [false, '\\App\\User'],
            [false, 'String'],
        ];
    }
}
