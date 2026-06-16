# Componenta Interceptor App

Application-level compile integration for `componenta/interceptor`. This package turns interceptor attributes into serializable descriptors that can be loaded by the runtime interceptor layer.

Use it during application cache builds. Runtime code that only invokes interceptors should depend on `componenta/interceptor`.

## Installation

```bash
composer require componenta/interceptor-app
```

The package declares `Componenta\Interceptor\App\ConfigProvider` in `extra.componenta.config-providers`.
When `componenta/composer-plugin` is installed, the provider is added to the generated provider list automatically.

## Related Packages

| Package | Why it matters here |
|---|---|
| `componenta/interceptor` | Executes interceptor chains and consumes compiled descriptors. |
| `componenta/class-finder` | Finds classes and methods with interceptor attributes. |
| `componenta/app` | Enables the compiler only when interceptor support is installed and bound. |

## What It Adds

The package provides `Componenta\Interceptor\App\Compile\InterceptorMapCompiler`.

The compiler scans discovered classes for:

- `#[Intercept]` attributes on methods
- attributes that implement interceptor contracts directly

It produces a descriptor map that can be passed to `AttributeInterceptor` from `componenta/interceptor`.

## Development Mode

In development, interceptor metadata may be discovered from source classes while the application cache is being built. This keeps method-level interception declarative without forcing runtime packages to depend on class scanning.

## Production Mode

In production, applications should load the compiled descriptor map. This avoids repeated reflection over methods and keeps interception setup deterministic.

The compiler should be enabled only when `componenta/interceptor` is installed and the attribute interceptor service is bound. Optional feature gating is handled by `componenta/app`.

## Boundaries

`componenta/interceptor-app` does not execute interceptor chains. Callable contexts, chain execution, interceptor attributes, factories, and runtime dispatch belong to `componenta/interceptor`; this package owns only descriptor compilation.
