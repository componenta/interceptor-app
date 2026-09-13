<?php

declare(strict_types=1);

use Componenta\Config\ConfigFactory;
use Componenta\Config\Environment;
use Componenta\DI\CallableExecutorInterface;
use Componenta\DI\ContainerFactory;
use Componenta\DI\Exception\ResolutionException;
use Componenta\DI\FactoryInterface;
use Componenta\Interceptor\AttributeInterceptor;
use Componenta\Interceptor\CallableContext;
use Componenta\Interceptor\ConfigProvider;
use Componenta\Interceptor\Http\Attribute\Respond;
use Componenta\Interceptor\InterceptingExecutor;
use Componenta\Interceptor\Scope;

final class ScopeIsolationTarget
{
    #[Respond]
    public function run(): string
    {
        return 'console result';
    }
}

it('skips HTTP dependencies outside the attribute scope on repeated calls', function (bool $prepared): void {
    $composition = (new ConfigFactory())->create(new Environment([]), new ConfigProvider());
    $container = (new ContainerFactory())->create($composition->config, $composition->dependencies);
    $pipeline = new InterceptingExecutor(
        $container->get(CallableExecutorInterface::class),
        new AttributeInterceptor(
            $container->get(FactoryInterface::class),
            $prepared ? [ScopeIsolationTarget::class . '::run' => [0]] : [],
        ),
    );
    $context = CallableContext::scoped(Scope::CONSOLE, [new ScopeIsolationTarget(), 'run']);

    expect($pipeline->handle($context))->toBe('console result');
    expect(fn () => $pipeline->handle($context->withAttribute(CallableContext::SCOPE_ATTRIBUTE, Scope::HTTP)))
        ->toThrow(ResolutionException::class, 'responseFactory');
    expect($pipeline->handle($context))->toBe('console result');
})->with(['source' => [false], 'prepared' => [true]]);
