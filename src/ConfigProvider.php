<?php

declare(strict_types=1);

namespace Componenta\Interceptor\App;

use Componenta\App\ConfigKey as AppConfigKey;
use Componenta\Config\ConfigProvider as BaseConfigProvider;
use Componenta\Interceptor\App\Build\InterceptorBuilder;
use Componenta\Interceptor\App\Build\InterceptorBuilderFactory;
use Componenta\Interceptor\App\Factory\AttributeInterceptorFactory;
use Componenta\Interceptor\AttributeInterceptor;

final class ConfigProvider extends BaseConfigProvider
{
    protected function getFactories(): array
    {
        return [
            InterceptorBuilder::class => InterceptorBuilderFactory::class,
            AttributeInterceptor::class => AttributeInterceptorFactory::class,
        ];
    }
    protected function getConfig(): array
    {
        return [AppConfigKey::BUILDERS => [InterceptorBuilder::class]];
    }
}
