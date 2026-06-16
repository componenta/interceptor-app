<?php

declare(strict_types=1);

use Componenta\Interceptor\AttributeInterceptor;
use Componenta\Interceptor\CallableContext;
use Componenta\Interceptor\App\Compile\InterceptorMapCompiler;
use Componenta\Interceptor\ContextHandler;
use Componenta\Interceptor\Tests\Fixture\FactoryStub;
use Componenta\Interceptor\Tests\Fixture\PassThroughExecutor;
use Componenta\Interceptor\Tests\Fixture\RecordingInterceptor;
use Componenta\Interceptor\Tests\Fixture\TargetClass;

function interceptorAppFactory(array &$log): FactoryStub
{
    $factory = new FactoryStub();
    $factory->bind(RecordingInterceptor::class, function (array $params) use (&$log) {
        return new RecordingInterceptor($params['name'], $log);
    });

    return $factory;
}

describe('InterceptorMapCompiler', function () {
    it('compiles metadata that the runtime attribute interceptor can execute', function () {
        $log = [];
        $map = (new InterceptorMapCompiler())->compile([TargetClass::class]);
        $interceptor = new AttributeInterceptor(interceptorAppFactory($log), $map);
        $terminal = new ContextHandler(new PassThroughExecutor());

        $single = $interceptor->intercept(new CallableContext([new TargetClass(), 'single']), $terminal);
        $direct = $interceptor->intercept(new CallableContext([new TargetClass(), 'direct']), $terminal);

        expect($single)->toBe('single')
            ->and($direct)->toBe('wrap:direct')
            ->and($log)->toBe(['A:before', 'A:after']);
    });
});
