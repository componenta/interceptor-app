<?php

declare(strict_types=1);

namespace Componenta\Interceptor\App\Compile;

use Componenta\Interceptor\Attribute\Intercept;
use Componenta\Interceptor\InterceptorInterface;
use ReflectionClass;
use ReflectionFunctionAbstract;

final class InterceptorMapCompiler
{
    /**
     * @param iterable<class-string> $classes
     * @return array<string, list<array<string, mixed>>>
     */
    public function compile(iterable $classes): array
    {
        $map = [];

        foreach ($classes as $class) {
            if (!is_string($class) || !class_exists($class)) {
                continue;
            }

            try {
                $reflection = new ReflectionClass($class);
            } catch (\ReflectionException) {
                continue;
            }

            foreach ($reflection->getMethods() as $method) {
                $descriptors = $this->compileCallable($method);

                if ($descriptors !== []) {
                    $map[$method->class . '::' . $method->name] = $descriptors;
                }
            }
        }

        ksort($map);

        return $map;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function compileCallable(ReflectionFunctionAbstract $reflection): array
    {
        $descriptors = [];

        foreach ($reflection->getAttributes() as $attribute) {
            $class = $attribute->getName();

            if (is_a($class, Intercept::class, true)) {
                $descriptors[] = [
                    'kind' => 'factory',
                    'attribute' => $class,
                    'arguments' => $attribute->getArguments(),
                ];

                continue;
            }

            if (is_a($class, InterceptorInterface::class, true)) {
                $descriptors[] = [
                    'kind' => 'direct',
                    'class' => $class,
                    'arguments' => $attribute->getArguments(),
                ];
            }
        }

        return $descriptors;
    }
}
