<?php

declare(strict_types=1);

namespace Componenta\Interceptor\App;

use Componenta\App\ConfigKey as AppConfigKey;
use Componenta\Config\ConfigProvider as BaseConfigProvider;
use Componenta\Interceptor\App\Compile\InterceptorMapCompiler;
use Componenta\Interceptor\App\Compile\InterceptorMapContributor;

final class ConfigProvider extends BaseConfigProvider
{
    protected function getInvokables(): array
    {
        return [
            InterceptorMapCompiler::class,
            InterceptorMapContributor::class,
        ];
    }

    protected function getConfig(): array
    {
        return [
            AppConfigKey::COMPILE_CACHE_CONTRIBUTORS => [
                InterceptorMapContributor::class,
            ],
        ];
    }
}
