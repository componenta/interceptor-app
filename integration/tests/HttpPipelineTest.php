<?php

declare(strict_types=1);

use Componenta\App\Console\Command\BuildCommand;
use Componenta\ClassFinder\ClassIterator;
use Componenta\Config\ConfigFactory;
use Componenta\Config\Environment;
use Componenta\DI\ContainerFactory;
use Componenta\Http\ResponderConfigProvider;
use Componenta\Interceptor\App\Build\InterceptorBuilder;
use Componenta\Interceptor\AttributeInterceptor;
use Componenta\Interceptor\CallableContext;
use Componenta\Interceptor\Http\Attribute\Respond;
use Componenta\Interceptor\Http\Paginate;
use Componenta\Interceptor\PipelineInterface;
use Componenta\Interceptor\Scope;
use Componenta\Interceptor\Serialization\Attribute\Serialize;
use Componenta\Stdlib\Paginator;
use Componenta\Stdlib\PathResolver;
use Componenta\Stdlib\PathResolverInterface;
use Componenta\Tokenizer\ClassInfo;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\ServerRequest;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Serializer\SerializerInterface;

final class HttpInterceptionTarget
{
    #[Respond(201, 'application/json')]
    #[Serialize]
    #[Paginate(Paginate::FIELD_COUNT, Paginate::FIELD_PAGE)]
    public function items(): Paginator
    {
        return new Paginator(['a', 'b'], limit: 2, offset: 2, totalCount: 6);
    }
}
final class IntegrationArrayableNormalizer implements NormalizerInterface
{
    public function getSupportedTypes(?string $format): array
    {
        return [\Componenta\Arrayable\Arrayable::class => true];
    }
    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        return $data instanceof \Componenta\Arrayable\Arrayable;
    }
    public function normalize(mixed $data, ?string $format = null, array $context = []): array
    {
        return $data->toArray();
    }
}
it('runs lazy app build and preserves the HTTP pipeline before and after building', function (): void {
    $root = sys_get_temp_dir() . '/interceptor_http_' . bin2hex(random_bytes(8));
    mkdir($root);
    $reads = 0;
    $builderConstructions = 0;
    $create = static function (bool $runtimeOnly = false) use ($root, &$reads, &$builderConstructions) {
        $source = new ClassIterator((static function () use ($runtimeOnly, &$reads): Generator {
            if ($runtimeOnly) {
                throw new RuntimeException('runtime discovery is forbidden');
            }
            ++$reads;
            yield new ClassInfo(HttpInterceptionTarget::class);
        })());
        $http = new Psr17Factory();
        $composition = (new ConfigFactory())->create(
            new Environment([]),
            new \Componenta\App\ConfigProvider(),
            new \Componenta\App\Console\ConfigProvider(),
            new \Componenta\Interceptor\ConfigProvider(),
            new \Componenta\Interceptor\App\ConfigProvider(),
            new ResponderConfigProvider(),
            static function () use ($root, $source, $http, &$builderConstructions): array {
                return [
                    'interceptors.map_file' => 'interceptors.php',
                    \Componenta\Interceptor\ConfigKey::HTTP_INTERCEPTORS => [AttributeInterceptor::class],
                    \Componenta\Config\ConfigKey::DEPENDENCIES => [
                        \Componenta\Config\ConfigKey::SERVICES => [
                            PathResolverInterface::class => new PathResolver($root),
                            \Componenta\App\ConfigKey::DISCOVERY_SOURCE => $source,
                            ResponseFactoryInterface::class => $http,
                            StreamFactoryInterface::class => $http,
                            SerializerInterface::class => new Serializer([new IntegrationArrayableNormalizer()], [new JsonEncoder()]),
                        ],
                        \Componenta\Config\ConfigKey::DELEGATORS => [
                            InterceptorBuilder::class => [static function (InterceptorBuilder $builder) use (&$builderConstructions): InterceptorBuilder {
                                ++$builderConstructions;
                                return $builder;
                            }],
                        ],
                    ],
                ];
            },
        );
        return (new ContainerFactory())->create($composition->config, $composition->dependencies);
    };
    $run = static function (PipelineInterface $pipeline): array {
        $target = new HttpInterceptionTarget();
        $request = new ServerRequest('GET', '/items?limit=2&offset=2');
        $context = CallableContext::scoped(Scope::HTTP, [$target, 'items'], attributes: [ServerRequestInterface::class => $request]);
        $response = $pipeline->handle($context);
        expect($response)->toBeInstanceOf(ResponseInterface::class);
        return [$response->getStatusCode(), $response->getHeaderLine('Content-Type'), json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR)];
    };
    try {
        $container = $create();
        $pipeline = $container->get(PipelineInterface::class);
        $expected = [201, 'application/json', ['count' => 6, 'page' => 2, 'results' => ['a', 'b']]];
        expect($run($pipeline))->toBe($expected);
        $application = new Application();
        $application->setAutoExit(false);
        $application->addCommand($container->get(BuildCommand::class));
        $output = new BufferedOutput();
        foreach ([['command' => 'list'], ['command' => 'app:build', '--help' => true]] as $input) {
            expect($application->run(new ArrayInput($input), $output))->toBe(0);
        }
        expect($reads)->toBe(0)->and($builderConstructions)->toBe(0);
        expect($application->run(new ArrayInput(['command' => 'app:build']), $output))->toBe(0);
        expect($reads)->toBe(1)->and($builderConstructions)->toBe(1);
        $prepared = $create(true)->get(PipelineInterface::class);
        expect($run($prepared))->toBe($expected)->and($run($prepared))->toBe($expected);
    } finally {
        if (is_file($root . '/interceptors.php')) {
            unlink($root . '/interceptors.php');
        }
        rmdir($root);
    }
});

it('loads the modified Componenta packages from the local packages directory', function (): void {
    foreach (['interceptor', 'interceptor-app', 'serialize-interceptor', 'http-respond-interceptor', 'http-paginate-interceptor', 'http-responder', 'di', 'config', 'app'] as $package) {
        expect(realpath(\Composer\InstalledVersions::getInstallPath('componenta/' . $package)))
            ->toBe(realpath(dirname(__DIR__, 3) . '/' . $package));
    }
});
