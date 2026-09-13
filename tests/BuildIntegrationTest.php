<?php

declare(strict_types=1);

use Componenta\App\Build\ApplicationBuildOrchestrator;
use Componenta\ClassFinder\ClassIterator;
use Componenta\Config\ConfigFactory;
use Componenta\Config\Environment;
use Componenta\DI\ContainerFactory;
use Componenta\Interceptor\AttributeInterceptor;
use Componenta\Interceptor\CallableContextInterface;
use Componenta\Interceptor\ContextHandlerInterface;
use Componenta\Interceptor\InterceptorInterface;
use Componenta\Interceptor\PipelineInterface;
use Componenta\Stdlib\PathResolver;
use Componenta\Stdlib\PathResolverInterface;
use Componenta\Tokenizer\ClassInfo;

#[Attribute(Attribute::TARGET_METHOD)]
final class BuildFreshAttribute implements InterceptorInterface
{
    private int $calls = 0;
    public function intercept(CallableContextInterface $context, ContextHandlerInterface $handler): mixed
    {
        return ++$this->calls . ':' . $handler->handle($context);
    }
}
final class BuildInterceptedTarget
{
    #[BuildFreshAttribute]
    public function run(): string
    {
        return 'value';
    }
}

function interceptorBuildContainer(string $root, ClassIterator $source): \Componenta\Config\ContainerValue
{
    $composition = (new ConfigFactory())->create(
        new Environment([]),
        new \Componenta\App\ConfigProvider(),
        new \Componenta\App\Console\ConfigProvider(),
        new \Componenta\Interceptor\ConfigProvider(),
        new \Componenta\Interceptor\App\ConfigProvider(),
        static fn (): array => [
            'interceptors.map_file' => 'interceptors.php',
            \Componenta\Interceptor\ConfigKey::HTTP_INTERCEPTORS => [AttributeInterceptor::class],
            \Componenta\Config\ConfigKey::DEPENDENCIES => [
                \Componenta\Config\ConfigKey::SERVICES => [
                    PathResolverInterface::class => new PathResolver($root),
                    \Componenta\App\ConfigKey::DISCOVERY_SOURCE => $source,
                ],
            ],
        ],
    );
    return (new ContainerFactory())->create($composition->config, $composition->dependencies);
}

it('builds interceptor metadata lazily and executes fresh attributes from the artifact', function (): void {
    $root = sys_get_temp_dir() . '/interceptor_build_' . bin2hex(random_bytes(8));
    mkdir($root);
    $reads = 0;
    $source = new ClassIterator((static function () use (&$reads): Generator {
        ++$reads;
        yield new ClassInfo(BuildInterceptedTarget::class);
    })());
    try {
        $container = interceptorBuildContainer($root, $source);
        $build = $container->get(ApplicationBuildOrchestrator::class);
        expect($reads)->toBe(0)->and(is_file($root . '/interceptors.php'))->toBeFalse();
        $build->build();
        expect(is_file($root . '/interceptors.php'))->toBeTrue()->and($reads)->toBe(1);
        $fresh = interceptorBuildContainer($root, new ClassIterator((static function (): Generator {
            throw new RuntimeException('Runtime must not scan source classes');
            yield;
        })()));
        $pipeline = $fresh->get(PipelineInterface::class);
        $target = new BuildInterceptedTarget();
        expect($pipeline->call([$target, 'run']))->toBe('1:value')
            ->and($pipeline->call([$target, 'run']))->toBe('1:value');
    } finally {
        if (is_file($root . '/interceptors.php')) {
            unlink($root . '/interceptors.php');
        }
        rmdir($root);
    }
});

it('executes source attributes when the map is missing or invalid without rebuilding it', function (?string $content): void {
    $root = sys_get_temp_dir() . '/interceptor_invalid_' . bin2hex(random_bytes(8));
    mkdir($root);
    $file = $root . '/interceptors.php';
    if ($content !== null) {
        file_put_contents($file, $content);
    }
    $source = new ClassIterator((static function (): Generator {
        throw new RuntimeException('must not scan');
        yield;
    })());
    try {
        $pipeline = interceptorBuildContainer($root, $source)->get(PipelineInterface::class);
        expect($pipeline->call([new BuildInterceptedTarget(), 'run']))->toBe('1:value');
        expect(is_file($file))->toBe($content !== null);
        if ($content !== null) {
            expect(file_get_contents($file))->toBe($content);
        }
    } finally {
        if (is_file($file)) {
            unlink($file);
        }
        rmdir($root);
    }
})->with([
    'missing' => [null],
    'syntax error' => ['<?php return ['],
    'non-array' => ['<?php return 42;'],
    'old envelope' => ['<?php return ["version"=>1,"map"=>[]];'],
    'invalid position' => ['<?php return ["BuildInterceptedTarget::run"=>["invalid"]];'],
]);

it('rebuilds from the original class source even if an earlier map omits attributes', function (): void {
    $root = sys_get_temp_dir() . '/interceptor_refresh_' . bin2hex(random_bytes(8));
    mkdir($root);
    $file = $root . '/interceptors.php';
    file_put_contents($file, '<?php return ["BuildInterceptedTarget::run"=>[]];');
    try {
        $source = new ClassIterator([new ClassInfo(BuildInterceptedTarget::class)]);
        $container = interceptorBuildContainer($root, $source);
        $container->get(ApplicationBuildOrchestrator::class)->build();
        $pipeline = interceptorBuildContainer($root, new ClassIterator([]))->get(PipelineInterface::class);
        expect($pipeline->call([new BuildInterceptedTarget(), 'run']))->toBe('1:value');
    } finally {
        unlink($file);
        rmdir($root);
    }
});

it('keeps every app build attempt failing and preserves the previous artifact on discovery failure', function (int $yieldCount): void {
    $root = sys_get_temp_dir() . '/interceptor_command_failure_' . bin2hex(random_bytes(8));
    mkdir($root);
    $file = $root . '/interceptors.php';
    file_put_contents($file, '<?php return [];');
    try {
        $source = new ClassIterator((static function () use ($yieldCount): Generator {
            for ($index = 0; $index < $yieldCount; ++$index) {
                yield new ClassInfo(BuildInterceptedTarget::class);
            }
            throw new RuntimeException('discovery failed');
        })());
        $container = interceptorBuildContainer($root, $source);
        $application = new \Symfony\Component\Console\Application();
        $application->setAutoExit(false);
        $application->addCommand($container->get(\Componenta\App\Console\Command\BuildCommand::class));
        for ($attempt = 0; $attempt < 2; ++$attempt) {
            $output = new \Symfony\Component\Console\Output\BufferedOutput();
            expect($application->run(new \Symfony\Component\Console\Input\ArrayInput(['command' => 'app:build']), $output))->toBe(1);
            expect($output->fetch())->toContain('discovery failed');
            expect(file_get_contents($file))->toBe('<?php return [];');
        }
    } finally {
        unlink($file);
        rmdir($root);
    }
})->with([0, 1, 2]);

final class BuildLateLoadedTarget
{
    #[BuildRuntimeLoadedInterceptorAttribute]
    #[BuildFreshAttribute]
    public function run(): string
    {
        return 'value';
    }
}

it('keeps attributes available for late loading after building and warming the pipeline', function (): void {
    $root = sys_get_temp_dir() . '/interceptor_late_build_' . bin2hex(random_bytes(8));
    mkdir($root);
    $loader = static function (string $class): void {
        if ($class === 'BuildRuntimeLoadedInterceptorAttribute') {
            require __DIR__ . '/Fixture/BuildRuntimeLoadedInterceptorAttribute.php';
        }
    };
    try {
        $container = interceptorBuildContainer($root, new ClassIterator([new ClassInfo(BuildLateLoadedTarget::class)]));
        $container->get(ApplicationBuildOrchestrator::class)->build();
        $pipeline = interceptorBuildContainer($root, new ClassIterator([]))->get(PipelineInterface::class);
        $callable = [new BuildLateLoadedTarget(), 'run'];
        expect($pipeline->call($callable))->toBe('1:value');

        spl_autoload_register($loader);
        expect($pipeline->call($callable))->toBe('loaded:1:value')
            ->and($pipeline->call($callable))->toBe('loaded:1:value');
    } finally {
        spl_autoload_unregister($loader);
        if (is_file($root . '/interceptors.php')) {
            unlink($root . '/interceptors.php');
        }
        rmdir($root);
    }
});
