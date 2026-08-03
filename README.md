# plato-api-contract

[English](README.md) | [简体中文](README.zh-CN.md)

Contract-first controller and OpenAPI 3.1 generation for PHP frameworks. The bundled platform is
PlatoPHP; another framework can be connected with a trusted PHP template pack. Contracts may use
lightweight `rules()` sets or application-owned readonly DTOs. The generator writes controllers,
scaffolds Logic once, and records generated ownership in a manifest.

## Installation and requirements

- PHP 8.2+
- PlatoPHP when using the bundled `plato` platform
- Node.js only for the development OpenAPI lint script

Install `lumnd/plato-api-contract` as a production dependency of the host application. Generated
Plato controllers and Logic signatures use this package's `Runtime\ApiContext`, `Runtime\Input` and
`Runtime\Dto` classes, so putting the package only in `require-dev` makes generated code fail after
`composer install --no-dev`.

This repository uses a sibling `../platophp` path checkout for development. To install and verify
the repository itself:

```bash
composer install
npm install
composer verify
```

## Contract

```php
use function Lumnd\PlatoApiContract\Dsl\post;
use function Lumnd\PlatoApiContract\Dsl\rules;

return [
    'syntax' => 'v1',
    'services' => [
        'auth' => post(
            '/auth/login',
            rules([
                'email'    => ['required', 'string', 'email'],
                'code'     => ['required', 'string', 'size:6'],
                'remember' => ['boolean', 'default:false'],
            ]),
            rules([
                'token'      => ['string'],
                'expires_at' => ['string', 'nullable'],
            ]),
            auth: 'none',
        ),
    ],
];
```

Rules read like a Laravel FormRequest and describe both sides. Every request field has to say
whether it may be absent - `required`, `nullable`, or a `default:` - and the generated controller
projects each one, so Logic always receives the declared key in the declared type. Logic gets plain
arrays and a matching PHPStan array shape on its skeleton.

Pass readonly DTO class names instead of `rules()` where typed request and response objects are
wanted; request and response choose independently. Both forms compile to the same IR and the same
OpenAPI document.

`auth` is `required` (the default), `optional`, or `none`, and is generated into the controller's
`$actions` for PlatoPHP to route on. There is no permission DSL, access registry, or duplicated
controller guard.

## Commands

```bash
vendor/bin/plato api:lint
vendor/bin/plato api:generate
vendor/bin/plato api:check
```

The three PlatoPHP commands read `api_contract` from the project root `plato.config.php`. The
standalone `vendor/bin/plato-api` reads `api-contract.php` and also accepts
`--config=/any/path/options.php`. Pass `--platform=/path/platform.php` to load a framework template
pack without registering a PHP adapter class.

Generated projects need only `contracts/` and, when overriding output, `templates/`. Runtime output
uses the application's existing controller and Logic directories; no `dto`, `contract`, or
`generated` directory is required.

Controllers and OpenAPI documents are generator-owned. Their hashes are committed in the manifest,
so generation refuses to overwrite an unexplained edit; move the edit into Logic or a template, or
use `--force` only when intentionally discarding it. Logic files are user-owned: they are scaffolded
once and are never read, overwritten or removed. `api:check` performs the same comparison without
writing and exits `4` when generated output is missing, stale, modified or obsolete.

See [DSL](docs/dsl.md), [configuration](docs/configuration.md), and [templates](docs/templates.md).
