<?php

declare(strict_types=1);

use Componenta\Interceptor\App\Compile\InterceptorMapContributor;
use Componenta\Interceptor\ConfigKey;
use Componenta\Interceptor\App\Tests\Fixture\TargetClass;

describe('InterceptorMapContributor', function () {
    it('returns the compiled interceptor map config delta', function () {
        $delta = (new InterceptorMapContributor())->compile([TargetClass::class]);

        expect($delta[ConfigKey::COMPILED_INTERCEPTORS])->toHaveKey(TargetClass::class . '::single');
    });

    it('omits the interceptor section when no metadata is discovered', function () {
        expect((new InterceptorMapContributor())->compile([]))->toBe([]);
    });
});
