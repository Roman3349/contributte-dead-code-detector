# Contributte Dead Code Detector

Extension for [shipmonk/dead-code-detector](https://github.com/shipmonk-rnd/dead-code-detector) (PHPStan) that teaches it about magic calls made by Contributte and Nette libraries, so their members are not reported as dead code.

## Content

- [Setup](#setup)
- [Configuration](#configuration)
- [Apitte](#apitte)
- [Contributte DI](#contributte-di)
- [Latte](#latte)

## Setup

Install the package using [Composer](https://getcomposer.org).

```bash
composer require --dev contributte/dead-code-detector
```

With [phpstan/extension-installer](https://github.com/phpstan/extension-installer), the extension is registered automatically. Otherwise, include both configs in your `phpstan.neon`.

```neon
includes:
	- vendor/shipmonk/dead-code-detector/rules.neon
	- vendor/contributte/dead-code-detector/extension.neon
```

## Configuration

Each provider is enabled automatically when its library is installed. You can enable or disable providers explicitly.

```neon
parameters:
	contributteDeadCode:
		apitte:
			enabled: true
		di:
			enabled: false
		latte:
			enabled: true
			templatePaths:
				- %currentWorkingDirectory%/templates
```

Apitte and Contributte DI providers read the Nette DI container configuration from the same option as the built-in Nette provider of the dead code detector.

```neon
parameters:
	shipmonkDeadCode:
		usageProviders:
			nette:
				containerNeonPaths:
					- %currentWorkingDirectory%/app/config/common.neon
					- %currentWorkingDirectory%/app/config/api.neon
```

## Apitte

- Endpoint methods of `IController` classes with the `#[Path]` attribute (or the `@Path` annotation in older versions).
- Constructors of request entities referenced via `#[RequestBody(entity: ...)]`. If `containerNeonPaths` is configured, constructors are marked only when `CoreMappingPlugin` is registered, because entities are instantiated only by the mapping. Without the configuration, the mapping is assumed to be enabled.
- Writes to public properties of request entities extending `Apitte\Core\Mapping\Request\BasicEntity`, filled by `BasicEntity::factory()`.
- Reads of public properties of response entities extending `Apitte\Core\Mapping\Response\BasicEntity`, serialized by `toResponse()`.
- Constructors of type mappers (`types`) and of the entity validator (`request.validator`) configured for `CoreMappingPlugin` (requires `containerNeonPaths`).

Constructors of controllers are not handled here. Controllers are Nette DI services, see the [Contributte DI](#contributte-di) provider and the built-in Nette provider.

## Contributte DI

- Constructors of services registered by [`ResourceExtension`](https://github.com/contributte/di/tree/master/.docs#resourceextension) (requires `containerNeonPaths`).

Classes are matched by the resource namespace and its `excludes`. The `paths` are not checked, as they usually contain dynamic parameters like `%appDir%`.

## Latte

- View objects passed to templates, including transitively referenced classes:
  - `Latte\Engine::render()`, `renderToString()`
  - Nette `Template::render()`, `renderToString()`, `add()`, `setParameters()` and `$template->foo = $viewModel`
- Public properties of typed templates extending `Nette\Bridges\ApplicationLatte\Template`.
- Classes declared in templates via [`{templateType}` and `{varType}`](https://latte.nette.org/en/type-system). `.latte` files are searched in the analysed paths and in `contributteDeadCode.latte.templatePaths`.
- Methods with `#[TemplateFilter]` and `#[TemplateFunction]` attributes.

All public methods and properties of classes reachable from templates are considered used, as the template can access any of them.

Latte support is also [proposed](https://github.com/shipmonk-rnd/dead-code-detector/pull/490) for the dead code detector itself. Once it is available there, this provider disables itself by default.
