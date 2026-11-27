<?php

declare(strict_types=1);

use Componenta\App\Build\ApplicationBuildOrchestrator;
use Componenta\App\Build\BuildStore;
use Componenta\App\ConfigKey as AppConfigKey;
use Componenta\ClassFinder\ClassIterator;
use Componenta\Config\ConfigFactory;
use Componenta\Config\ConfigKey;
use Componenta\Config\ContainerValue;
use Componenta\Config\Environment;
use Componenta\DI\ContainerFactory;
use Componenta\Interceptor\AttributeInterceptor;
use Componenta\Interceptor\CallableContextInterface;
use Componenta\Interceptor\ContextHandlerInterface;
use Componenta\Interceptor\InterceptorInterface;
use Componenta\Interceptor\PipelineInterface;

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
    public function run(): string { return 'value'; }
}

function interceptorBuildContainer(string $root, bool $forceSource = false): ContainerValue
{
    $source = new ClassIterator((static function (): Generator {
        throw new RuntimeException('Interceptor integration must not scan application classes.');
        yield;
    })());
    $composition = (new ConfigFactory())->create(
        new Environment(['APP_DISCOVERY_SOURCE' => $forceSource]),
        new \Componenta\App\ConfigProvider(),
        new \Componenta\Interceptor\ConfigProvider(),
        new \Componenta\Interceptor\App\ConfigProvider(),
        static fn (): array => [
            // Old settings are ignored by the migration provider.
            'interceptors.map_file' => $root . '/interceptors.php',
            \Componenta\Interceptor\ConfigKey::HTTP_INTERCEPTORS => [AttributeInterceptor::class],
            ConfigKey::DEPENDENCIES => [
                ConfigKey::FACTORIES => [BuildStore::class => static fn () => new BuildStore($root . '/build')],
                ConfigKey::SERVICES => [AppConfigKey::DISCOVERY_SOURCE => $source],
            ],
        ],
    );
    return (new ContainerFactory())->create($composition->config, $composition->dependencies);
}

function removeInterceptorFixture(string $root): void
{
    if (!str_starts_with($root, sys_get_temp_dir() . '/interceptor_')) {
        throw new LogicException('Unexpected interceptor fixture directory.');
    }
    if (!is_dir($root)) { return; }
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($files as $file) {
        if ($file->isDir() && !$file->isLink()) { rmdir($file->getPathname()); }
        else { unlink($file->getPathname()); }
    }
    rmdir($root);
}

it('retains native fresh attributes before and after an application build without preparing metadata', function (): void {
    $root = sys_get_temp_dir() . '/interceptor_native_' . bin2hex(random_bytes(8));
    try {
        $container = interceptorBuildContainer($root);
        expect($container->config->get(AppConfigKey::BUILDERS, []))->toBe([]);
        $target = new BuildInterceptedTarget();
        $pipeline = $container->get(PipelineInterface::class);
        expect($pipeline->call([$target, 'run']))->toBe('1:value')
            ->and($pipeline->call([$target, 'run']))->toBe('1:value');
        $prepared = $container->get(ApplicationBuildOrchestrator::class)->build();
        expect($prepared->read('interceptor.attributes'))->toBeNull()
            ->and($prepared->skipped())->toBe([])
            ->and(is_file($root . '/interceptors.php'))->toBeFalse();
        expect(interceptorBuildContainer($root)->get(PipelineInterface::class)->call([$target, 'run']))->toBe('1:value');
    } finally {
        removeInterceptorFixture($root);
    }
});

it('ignores legacy maps in ordinary and forced source mode without executing or rewriting them', function (?string $content, bool $forceSource): void {
    $root = sys_get_temp_dir() . '/interceptor_legacy_' . bin2hex(random_bytes(8));
    mkdir($root);
    $file = $root . '/interceptors.php';
    if ($content !== null) { file_put_contents($file, $content); }
    try {
        $pipeline = interceptorBuildContainer($root, $forceSource)->get(PipelineInterface::class);
        expect($pipeline->call([new BuildInterceptedTarget(), 'run']))->toBe('1:value');
        expect(is_file($file))->toBe($content !== null)
            ->and(is_dir($root . '/build'))->toBeFalse();
        if ($content !== null) { expect(file_get_contents($file))->toBe($content); }
    } finally {
        removeInterceptorFixture($root);
    }
})->with([
    'missing' => [null],
    'syntax error' => ['<?php return ['],
    'obsolete empty metadata' => ['<?php return ["BuildInterceptedTarget::run" => []];'],
    'executable' => ['<?php throw new LogicException("Legacy map executed.");'],
])->with([false, true]);

final class BuildLateLoadedTarget
{
    #[BuildRuntimeLoadedInterceptorAttribute]
    #[BuildFreshAttribute]
    public function run(): string { return 'value'; }
}

it('keeps attributes available for late loading after warming the native pipeline', function (): void {
    $root = sys_get_temp_dir() . '/interceptor_late_' . bin2hex(random_bytes(8));
    $loader = static function (string $class): void {
        if ($class === 'BuildRuntimeLoadedInterceptorAttribute') {
            require __DIR__ . '/Fixture/BuildRuntimeLoadedInterceptorAttribute.php';
        }
    };
    try {
        $pipeline = interceptorBuildContainer($root)->get(PipelineInterface::class);
        $callable = [new BuildLateLoadedTarget(), 'run'];
        expect($pipeline->call($callable))->toBe('1:value');
        spl_autoload_register($loader);
        expect($pipeline->call($callable))->toBe('loaded:1:value')
            ->and($pipeline->call($callable))->toBe('loaded:1:value');
    } finally {
        spl_autoload_unregister($loader);
        removeInterceptorFixture($root);
    }
});
