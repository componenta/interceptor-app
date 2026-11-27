<?php

declare(strict_types=1);

namespace Componenta\Interceptor\App;

use Componenta\Config\ConfigProvider as BaseConfigProvider;

/**
 * Migration provider for applications that still register this package.
 * Interceptor 4 owns its native attribute metadata and runtime factory.
 */
final class ConfigProvider extends BaseConfigProvider
{
}
