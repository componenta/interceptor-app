<?php

declare(strict_types=1);

#[Attribute(Attribute::TARGET_METHOD)]
final class BuildRuntimeLoadedInterceptorAttribute implements \Componenta\Interceptor\InterceptorInterface
{
    public function intercept(\Componenta\Interceptor\CallableContextInterface $context, \Componenta\Interceptor\ContextHandlerInterface $handler): mixed
    {
        return 'loaded:' . $handler->handle($context);
    }
}
