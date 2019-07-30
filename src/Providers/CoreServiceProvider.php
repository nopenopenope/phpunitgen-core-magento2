<?php

declare(strict_types=1);

namespace PhpUnitGen\Core\Providers;

use League\Container\Definition\DefinitionInterface;
use League\Container\ReflectionContainer;
use League\Container\ServiceProvider\AbstractServiceProvider;
use League\Container\ServiceProvider\BootableServiceProviderInterface;
use PhpUnitGen\Core\Contracts\Aware\ClassFactoryAware;
use PhpUnitGen\Core\Contracts\Aware\ConfigAware;
use PhpUnitGen\Core\Contracts\Aware\DocumentationFactoryAware;
use PhpUnitGen\Core\Contracts\Aware\ImportFactoryAware;
use PhpUnitGen\Core\Contracts\Aware\MethodFactoryAware;
use PhpUnitGen\Core\Contracts\Aware\MockGeneratorAware;
use PhpUnitGen\Core\Contracts\Aware\PropertyFactoryAware;
use PhpUnitGen\Core\Contracts\Aware\StatementFactoryAware;
use PhpUnitGen\Core\Contracts\Aware\TestGeneratorAware;
use PhpUnitGen\Core\Contracts\Aware\ValueFactoryAware;
use PhpUnitGen\Core\Contracts\Config\Config;
use PhpUnitGen\Core\Contracts\Generators\Factories\ClassFactory as ClassFactoryContract;
use PhpUnitGen\Core\Contracts\Generators\Factories\DocumentationFactory as DocumentationFactoryContract;
use PhpUnitGen\Core\Contracts\Generators\Factories\ImportFactory as ImportFactoryContract;
use PhpUnitGen\Core\Contracts\Generators\Factories\MethodFactory as MethodFactoryContract;
use PhpUnitGen\Core\Contracts\Generators\Factories\PropertyFactory as PropertyFactoryContract;
use PhpUnitGen\Core\Contracts\Generators\Factories\StatementFactory as StatementFactoryContract;
use PhpUnitGen\Core\Contracts\Generators\Factories\ValueFactory as ValueFactoryContract;
use PhpUnitGen\Core\Contracts\Generators\MockGenerator as MockGeneratorContract;
use PhpUnitGen\Core\Contracts\Generators\TestGenerator as TestGeneratorContract;
use PhpUnitGen\Core\Contracts\Parsers\CodeParser as CodeParserContract;
use PhpUnitGen\Core\Contracts\Renderers\Renderer as RendererContract;
use PhpUnitGen\Core\Exceptions\InvalidArgumentException;
use PhpUnitGen\Core\Generators\Mocks\MockeryMockGenerator;
use PhpUnitGen\Core\Helpers\Str;
use PhpUnitGen\Core\Parsers\CodeParser;
use PhpUnitGen\Core\Renderers\Renderer;
use ReflectionClass;
use ReflectionException;
use ReflectionMethod;
use ReflectionParameter;

/**
 * Class CoreServiceProvider.
 *
 * This service provider will provides all contracts implementations mapping
 * to the container it is registered on.
 *
 * @author  Paul Thébaud <paul.thebaud29@gmail.com>
 * @author  Killian Hascoët <killianh@live.fr>
 * @license MIT
 */
class CoreServiceProvider extends AbstractServiceProvider implements BootableServiceProviderInterface
{
    /**
     * The contracts that this service provider must have defined at the registration end.
     */
    protected const REQUIRED_CONTRACTS = [
        ClassFactoryContract::class,
        CodeParserContract::class,
        DocumentationFactoryContract::class,
        ImportFactoryContract::class,
        MethodFactoryContract::class,
        MockGeneratorContract::class,
        PropertyFactoryContract::class,
        RendererContract::class,
        StatementFactoryContract::class,
        TestGeneratorContract::class,
        ValueFactoryContract::class,
    ];

    /**
     * The aware contracts that should be inflected on container and the contract they provide.
     */
    protected const AWARE_CONTRACTS = [
        ClassFactoryAware::class         => ClassFactoryContract::class,
        ConfigAware::class               => Config::class,
        DocumentationFactoryAware::class => DocumentationFactoryContract::class,
        ImportFactoryAware::class        => ImportFactoryContract::class,
        MethodFactoryAware::class        => MethodFactoryContract::class,
        MockGeneratorAware::class        => MockGeneratorContract::class,
        PropertyFactoryAware::class      => PropertyFactoryContract::class,
        StatementFactoryAware::class     => StatementFactoryContract::class,
        TestGeneratorAware::class        => TestGeneratorContract::class,
        ValueFactoryAware::class         => ValueFactoryContract::class,
    ];

    /**
     * The default implementations which will be used if not provided in configuration.
     */
    protected const DEFAULT_IMPLEMENTATIONS = [
        CodeParserContract::class    => CodeParser::class,
        MockGeneratorContract::class => MockeryMockGenerator::class,
        RendererContract::class      => Renderer::class,
    ];

    /**
     * @var Config
     */
    protected $config;

    /**
     * CoreServiceProvider constructor.
     *
     * @param Config $config
     */
    public function __construct(Config $config)
    {
        $this->config = $config;

        $this->provides = self::REQUIRED_CONTRACTS;
        array_unshift($this->provides, Config::class);
    }

    /**
     * {@inheritdoc}
     */
    public function boot()
    {
        $this->addInflectors();
    }

    /**
     * {@inheritdoc}
     */
    public function register()
    {
        $this->leagueContainer->delegate(new ReflectionContainer());

        $this->leagueContainer->add(Config::class, $this->config);

        $this->addDefinitions();
    }

    /**
     * Add the contracts implementations to container and verify they are all declared.
     */
    protected function addDefinitions(): void
    {
        $implementations = array_merge(
            self::DEFAULT_IMPLEMENTATIONS,
            $this->config->implementations()
        );

        foreach ($implementations as $contract => $concrete) {
            $this->addDefinition($contract, $concrete);
        }

        if (array_diff(self::REQUIRED_CONTRACTS, array_keys($implementations))) {
            throw new InvalidArgumentException('missing contract implementation in config');
        }
    }

    /**
     * Add the inflector for all *Aware contracts.
     */
    protected function addInflectors(): void
    {
        foreach (self::AWARE_CONTRACTS as $awareContract => $providedContract) {
            $setter = 'set'.Str::replaceLast('Aware', '', Str::afterLast('\\', $awareContract));

            $this->leagueContainer->inflector($awareContract)
                ->invokeMethod($setter, [$providedContract]);
        }
    }

    /**
     * Add a contract's implementation to container with its arguments using reflection.
     *
     * @param string $contract
     * @param string $concrete
     *
     * @throws InvalidArgumentException
     */
    protected function addDefinition(string $contract, string $concrete): void
    {
        if (! in_array($contract, $this->provides)) {
            throw new InvalidArgumentException("contract {$contract} implementation is not necessary");
        }

        if (! class_exists($concrete)) {
            throw new InvalidArgumentException("class {$concrete} does not exists");
        }

        if (! in_array($contract, class_implements($concrete))) {
            throw new InvalidArgumentException("class {$concrete} does not implements {$contract}");
        }

        $definition = $this->leagueContainer->add($contract, $concrete);

        $this->addDefinitionArguments($definition);
    }

    /**
     * Add the necessary arguments to the definition.
     *
     * @param DefinitionInterface $definition
     *
     * @throws InvalidArgumentException
     */
    protected function addDefinitionArguments(DefinitionInterface $definition): void
    {
        $constructor = $this->getClassConstructor($definition);

        if (! $constructor) {
            return;
        }

        foreach ($constructor->getParameters() as $parameter) {
            $this->addDefinitionArgument($definition, $parameter);
        }
    }

    /**
     * Add an argument to definition from the given parameter.
     *
     * @param DefinitionInterface $definition
     * @param ReflectionParameter $parameter
     *
     * @throws InvalidArgumentException
     */
    protected function addDefinitionArgument(DefinitionInterface $definition, ReflectionParameter $parameter): void
    {
        $type = $parameter->getType();
        if (! $type || $type->isBuiltin()) {
            throw new InvalidArgumentException(
                "dependency {$parameter->getName()} for class {$definition->getConcrete()} has an unresolvable type"
            );
        }

        $definition->addArgument((string) $type);
    }

    /**
     * Get the constructor for a definition concrete class.
     *
     * @param DefinitionInterface $definition
     *
     * @return ReflectionMethod|null
     *
     * @throws InvalidArgumentException
     */
    protected function getClassConstructor(DefinitionInterface $definition): ?ReflectionMethod
    {
        try {
            return (new ReflectionClass($definition->getConcrete()))->getMethod('__construct');
        } catch (ReflectionException $exception) {
            return null;
        }
    }
}
