<?php

declare(strict_types=1);

namespace PhpUnitGen\Core\Generators\Mocks;

use PhpUnitGen\Core\Contracts\Generators\MockGenerator;
use PhpUnitGen\Core\Generators\Concerns\UsesImports;
use PhpUnitGen\Core\Models\TestClass;
use PhpUnitGen\Core\Models\TestMethod;
use PhpUnitGen\Core\Models\TestProperty;
use PhpUnitGen\Core\Models\TestStatement;
use Roave\BetterReflection\Reflection\ReflectionParameter;

/**
 * Class PhpUnitMockGenerator.
 *
 * The mock generator for PHPUnit.
 *
 * @package PhpUnitGen\Core
 * @author  Paul Thébaud <paul.thebaud29@gmail.com>
 * @author  Killian Hascoët <killianh@live.fr>
 * @license MIT
 */
class PhpUnitMockGenerator implements MockGenerator
{
    use UsesImports;

    /**
     * {@inheritDoc}
     */
    public function generateProperty(TestClass $class, ReflectionParameter $parameter): void
    {
        $type = $parameter->getType();
        if (! $type || $type->isBuiltin()) {
            return;
        }

        new TestProperty(
            $class,
            $parameter->getName() . 'Mock',
            $this->importClass($class, 'PHPUnit\\Framework\\MockObject\\MockObject')
        );
    }

    /**
     * {@inheritDoc}
     */
    public function generateStatement(TestMethod $method, ReflectionParameter $parameter): void
    {
        $type = $parameter->getType();
        if (! $type || $type->isBuiltin()) {
            return;
        }

        $classImport = $this->importClass($method->getTestClass(), (string) $type);

        new TestStatement(
            $method,
            "\$this->{$parameter->getName()}Mock = \$this->getMockBuilder({$classImport}::class)->getMock();"
        );
    }
}
