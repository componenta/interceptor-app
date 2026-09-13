<?php

declare(strict_types=1);

use Componenta\Config\ConfigFactory;
use Componenta\Config\Environment;
use Componenta\DI\Attribute\CurrentRequest;
use Componenta\DI\Attribute\CurrentUri;
use Componenta\DI\CallableExecutorInterface;
use Componenta\DI\ContainerFactory;
use Componenta\Interceptor\CallableContext;
use Componenta\Interceptor\ConfigProvider;
use Componenta\Interceptor\PipelineInterface;
use Componenta\Interceptor\Scope;
use Nyholm\Psr7\ServerRequest;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UriInterface;

it('preserves the current HTTP source through prepared invocation on repeated requests', function (bool $uri): void {
    $composition = (new ConfigFactory())->create(new Environment([]), new ConfigProvider());
    $container = (new ContainerFactory())->create($composition->config, $composition->dependencies);
    $pipeline = $container->get(PipelineInterface::class);
    $executor = $container->get(CallableExecutorInterface::class);
    $callable = $uri
        ? static fn (#[CurrentUri] UriInterface $value): UriInterface => $value
        : static fn (#[CurrentRequest] ServerRequestInterface $value): ServerRequestInterface => $value;

    foreach (['first', 'second'] as $path) {
        $request = new ServerRequest('GET', 'https://example.test/' . $path);
        $parameters = [ServerRequestInterface::class => $request];
        $context = CallableContext::scoped(Scope::HTTP, $callable, $parameters);
        $expected = $uri ? $request->getUri() : $request;

        expect($executor->call($callable, $parameters))->toBe($expected)
            ->and($pipeline->handle($context))->toBe($expected);
    }
})->with(['request' => [false], 'uri' => [true]]);
