# Template packs and overrides

## Connecting a framework

A framework can be implemented by a trusted local PHP definition and native PHP templates. The
definition declares its routing convention, helpers and output files; it does not need a custom
`PlatformAdapter` class.

```php
<?php

use Lumnd\PlatoApiContract\Contract\StandardRouteConvention;
use Lumnd\PlatoApiContract\Generation\Ownership;
use Lumnd\PlatoApiContract\Generation\TemplateContext;
use Lumnd\PlatoApiContract\Generation\TemplatePack;
use Lumnd\PlatoApiContract\Php\PhpTypes;

return TemplatePack::define('my-framework', new StandardRouteConvention(), __DIR__)
    ->helper('types', new PhpTypes())
    ->file('routes', 'routes/api.php')
    ->eachApi(
        'controller',
        static fn (TemplateContext $view): string => 'app/Http/' . $view->api?->name . '.php',
    )
    ->eachOperation(
        'logic',
        static fn (TemplateContext $view): string => 'app/Logic/' . $view->api?->name . '_'
            . $view->operation?->action . '.php',
        Ownership::User,
    );
```

Run it with `--platform=/path/to/platform.php`, or set `platform` in `api-contract.php`.

Every pack template receives `$view` as `TemplateContext` and `$helpers` as `TemplateHelpers`.
Depending on its declared scope, the context contains the normalized contract collection, current
API and current operation. Collection, API and operation templates are rendered once, once per API,
and once per operation respectively. Templates are executable PHP and must be treated as trusted
project code.

Core currently provides `PhpTypes`, `PhpImports`, `PhpExport`, DTO runtime helpers and the standard
route convention. A pack can register additional helper objects with `helper()`.

## Overriding the bundled Plato layout

The Plato adapter ships three native PHP templates: `controller.php`, `action.php`, and `logic.php`.
A project override directory may contain only the files it changes; built-ins remain the fallback.

```php
'api_contract' => [
    'templates' => __DIR__ . '/api/templates',
],
```

`controller.php` receives `ControllerView`, `action.php` receives `ActionView`, and `logic.php`
receives `LogicView`. Views are readonly and versioned through `TemplateApi`.

The action view exposes resolved input reads, validation, request construction, Logic invocation,
the projected response data, and the default response expression. A project can therefore express
its own response envelope and validation failure shape in one action template without adding runtime
strategy classes.

Reach for an override only when configuration cannot say it. The two things a project usually wants
are already answers to a question it is asked: `exception` decides what a refused request answers
(see [configuration](configuration.md#how-a-refused-request-is-answered)), and the `response-writer`
strategy decides what a successful one answers. The five supported strategy roles and their
interface requirements are listed under
[Plato strategy classes](configuration.md#plato-strategy-classes). Both approaches survive an
upgrade of the built-in templates; a copied action template does not.

`failureStatement` is a complete statement, semicolon included, rather than an expression: refusing
is not always answering, and a project that registered an exception throws from there.

`requestExpression` builds the request out of `$input`, the values the action read for the fields
the contract declared, and not out of the validator's own output. The validator answers whether the
input is acceptable; it does not answer what an absent optional field becomes, and
`validate::validated()` returns only the fields that carried a rule. An override that swaps one for
the other would silently drop every field with nothing to check.

The logic view carries `requestShape` and `responseShape`: PHPStan array shapes for a rules-based
operation, and empty strings for a DTO one, which types itself.

Plato layout overrides cannot choose output paths or ownership. A complete Template Pack declares
both in `platform.php`. Generated files are protected by the manifest; `Ownership::User` files are
scaffolded once and never overwritten after creation.

Changing any active template changes the generation fingerprint. A missing override directory, a
template error, or invalid generated PHP aborts before files are published.
