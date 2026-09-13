<?php

declare(strict_types=1);

namespace Componenta\Interceptor\App\Build;

use Componenta\App\ConfigKey;
use Componenta\ClassFinder\ClassIterator;
use Componenta\ClassFinder\ClassIteratorInterface;
use Componenta\Config\ContainerValue;
use Componenta\Interceptor\App\Factory\AttributeInterceptorFactory;

final class InterceptorBuilderFactory
{
    public function __invoke(ContainerValue $container): InterceptorBuilder
    {
        return new InterceptorBuilder(
            $container->has(ConfigKey::DISCOVERY_SOURCE)
                ? $container->get(ConfigKey::DISCOVERY_SOURCE, ClassIteratorInterface::class)
                : new ClassIterator([]),
            AttributeInterceptorFactory::mapFile($container),
        );
    }
}
