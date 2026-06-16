<?php

declare(strict_types=1);

use Componenta\App\ConfigKey as AppConfigKey;
use Componenta\Config\ConfigKey as DependencyConfigKey;
use Componenta\Interceptor\App\Compile\InterceptorMapCompiler;
use Componenta\Interceptor\App\Compile\InterceptorMapContributor;
use Componenta\Interceptor\App\ConfigProvider;

describe('interceptor app ConfigProvider', function () {
    it('registers the interceptor compile cache contributor', function () {
        $config = (new ConfigProvider())();

        expect($config[DependencyConfigKey::DEPENDENCIES][DependencyConfigKey::INVOKABLES])->toBe([
            InterceptorMapCompiler::class,
            InterceptorMapContributor::class,
        ])->and($config[AppConfigKey::COMPILE_CACHE_CONTRIBUTORS])->toBe([
            InterceptorMapContributor::class,
        ]);
    });
});
