# Componenta Interceptor App

Application build integration for Interceptor 3, App 4, DI 5 and Config 3.

Register `Componenta\Interceptor\ConfigProvider` and then `Componenta\Interceptor\App\ConfigProvider`. Composer metadata continues to expose the App provider for generated provider lists.

The provider registers `InterceptorBuilder` in `app.builders`. Run the application's ordinary command:

```bash
php bin/console.php app:build
```

`list` and `--help` do not create the builder. The builder receives the existing `app.discovery.source` class iterator and the path resolved by `PathResolverInterface`. It creates directories and publishes its own artifact by a temporary file and atomic rename. Source or write failure preserves the previously published file.

Configure the path through `Componenta\Interceptor\App\ConfigKey::MAP_FILE` (`interceptors.map_file`). Default: `var/cache/build/interceptors.php`.

## Artifact and runtime

The artifact is a plain array of method signatures and ordered positions of relevant native PHP attributes:

```php
return [
    'App\\Controller::show' => [0, 2],
    'App\\Controller::plain' => [],
];
```

The map stores no instantiated attributes, constructor arguments, services or interceptor instances. Runtime selects native `ReflectionAttribute` objects and calls `newInstance()` on each invocation. This preserves fresh object arguments, target/repeatability checks, exceptions, declaration order and scope behavior.

If an attribute class is unavailable during build, the method is omitted from the map and uses runtime metadata. Runtime rechecks incomplete classification so later autoloader registration can activate the attribute.

Methods absent from the map and named functions/closures use their own native metadata. Missing, malformed or unreadable artifacts fall back to source metadata. Runtime never invokes a builder or scans application classes. Publish code and its rebuilt artifact together; a structurally valid artifact from an earlier deployment is not a source freshness check.

`InterceptorMapCompiler`, `InterceptorMapContributor` and the compile-contributor registration API have been removed. Replace the old integration with this ConfigProvider and rebuild with `app:build`.

## Verification

Install sibling packages under `packages`, then:

```bash
composer --working-dir=integration install
composer --working-dir=integration test
composer --working-dir=integration test:packages
composer --working-dir=integration analyse
```

The integration runs actual ConfigProvider/DI, console build, Symfony Serializer, PSR-7 responses and pagination against the local packages.
