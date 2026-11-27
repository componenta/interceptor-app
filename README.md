# Componenta Interceptor App

Interceptor App 4 is a migration provider for applications upgrading to
Interceptor 4, Config 4 and PHP 8.5.13+. Native interceptor metadata belongs to
`componenta/interceptor`; this package no longer has an application build phase.

## Runtime registration

Register `Componenta\Interceptor\ConfigProvider`. It owns the native attribute
interceptor, callable-context factory and HTTP pipeline factory. Configure
`Componenta\Interceptor\ConfigKey::HTTP_INTERCEPTORS` in the application as usual.

Existing applications may continue registering
`Componenta\Interceptor\App\ConfigProvider`. This provider adds no factories or
builders, and its Composer provider metadata remains available during migration.
New applications can omit `componenta/interceptor-app` entirely.

Interceptor 4 caches native attribute descriptors at runtime. It materializes
attributes and constructor arguments only when the corresponding continuation is
reached. Each invocation receives fresh attribute instances; service interceptor
lifetimes remain owned by the configured factory and container. Methods with
incomplete attribute classification are rechecked when a later autoloader makes
an attribute class available. No application class scan or metadata artifact is
needed for this behavior.

## Migration from Interceptor App 3

Remove registrations and direct calls for:

- `Build\InterceptorBuilder` and `Build\InterceptorBuilderFactory`.
- `Factory\AttributeInterceptorFactory` from this package; retain the factory from
  the base Interceptor provider.
- `ConfigKey::MAP_FILE`, `ConfigKey::DEFAULT_MAP_FILE`, and `interceptors.map_file`.

The old `interceptors.php` files are ignored. They may be removed as deployment
cleanup; the new provider never reads, rewrites or removes them. `app:build` does
not produce an interceptor artifact. `app:clean` handles the application store,
without requiring any package-specific interceptor cleanup.

This also replaces the older `InterceptorMapCompiler`, `InterceptorMapContributor`
and compile-contributor registration APIs. Attribute positions are not a public
Interceptor 4 preparation contract.

## Verification

```bash
composer test
composer --working-dir=integration install
composer --working-dir=integration test
```

The package tests compose the actual Config 4 / DI 6 / Interceptor 4 providers and
verify fresh attributes, late autoloading, ignored legacy maps and application
build behavior without interceptor discovery. The optional integration suite
exercises Symfony Serializer, PSR-7 responses and pagination. Its sibling package
checkouts must match the target versions in `integration/composer.json`; the
version map does not imply that those releases have already been published.
