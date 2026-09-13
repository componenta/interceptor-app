<?php

declare(strict_types=1);

namespace Componenta\Interceptor\App\Factory;

use Componenta\Config\ContainerValue;
use Componenta\DI\FactoryInterface;
use Componenta\Interceptor\App\ConfigKey;
use Componenta\Interceptor\AttributeInterceptor;
use Componenta\Interceptor\Internal\AttributeMetadata;
use Componenta\Stdlib\PathResolverInterface;
use ErrorException;
use InvalidArgumentException;
use Throwable;

final class AttributeInterceptorFactory
{
    public function __invoke(ContainerValue $container): AttributeInterceptor
    {
        return new AttributeInterceptor(
            $container->get(FactoryInterface::class, FactoryInterface::class),
            self::read(self::mapFile($container)),
        );
    }

    public static function mapFile(ContainerValue $container): string
    {
        $path = $container->config->get(ConfigKey::MAP_FILE, ConfigKey::DEFAULT_MAP_FILE);
        if (!is_string($path) || trim($path) === '') {
            throw new InvalidArgumentException(ConfigKey::MAP_FILE . ' must be a non-empty path.');
        }
        return $container->get(PathResolverInterface::class, PathResolverInterface::class)->resolve($path);
    }

    /** @return array<string, list<int>> */
    private static function read(string $file): array
    {
        if (!is_file($file) || !is_readable($file)) {
            return [];
        }
        set_error_handler(static function (int $severity, string $message, string $file, int $line): never {
            throw new ErrorException($message, 0, $severity, $file, $line);
        });
        try {
            $map = (static fn (string $path): mixed => require $path)($file);
            return AttributeMetadata::validMap($map) ? $map : [];
        } catch (Throwable) {
            return [];
        } finally {
            restore_error_handler();
        }
    }
}
