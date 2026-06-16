<?php

declare(strict_types=1);

namespace Componenta\Interceptor\App\Compile;

use Componenta\App\Discovery\Compile\CompileCacheContributorInterface;
use Componenta\Interceptor\ConfigKey;

final readonly class InterceptorMapContributor implements CompileCacheContributorInterface
{
    /**
     * @param list<class-string> $classes
     *
     * @return array<string, mixed>
     */
    public function compile(array $classes): array
    {
        return [
            ConfigKey::COMPILED_INTERCEPTORS => (new InterceptorMapCompiler())->compile($classes),
        ];
    }
}
