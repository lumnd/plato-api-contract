# Project configuration

`api:lint`, `api:generate`, and `api:check` use one resolver. The standalone CLI reads a direct
option map from project-root `api-contract.php`:

```php
return [
    'contracts' => __DIR__ . '/api/contracts',
    'output' => __DIR__,
    'platform' => __DIR__ . '/api/templates/platform.php',
    'openapi' => 'docs/api/openapi.json',
    'manifest' => 'api/manifest.json',
];
```

## Option reference

Command line values override file values. Configuration keys use the same hyphenated spelling as
their long command line options:

| Key | Default | Meaning |
| --- | --- | --- |
| `contracts` | `api/contracts` | recursively loaded contract directory |
| `output` | project root | root containing every generated artifact |
| `platform` | none | trusted PHP platform definition file; takes precedence over `adapter` |
| `adapter` | `plato` | bundled named platform |
| `controller-namespace` | `control` | Plato generated controller namespace |
| `logic-namespace` | `logic` | Plato user-owned Logic namespace |
| `controller-dir` | `app/control` | controller directory below `output` |
| `logic-dir` | `app/logic` | Logic directory below `output` |
| `templates` | none | Plato layout override directory |
| `openapi` | `docs/api/openapi.json` | OpenAPI path below `output` |
| `manifest` | `api/manifest.json` | generation manifest path below `output` |
| `base-path` | empty | prefix added to every documented OpenAPI path |
| `path-suffix` | empty | suffix added to every documented OpenAPI path |
| `title` | `API` | OpenAPI `info.title` |
| `api-version` | `1.0.0` | OpenAPI `info.version` |
| `strategies` | empty map | Plato strategy role to application class name |
| `exception` | none | Plato validation refusal class |

`--config=PATH` may point anywhere. An explicit standalone file can return the option map directly,
or a complete Plato configuration containing `api_contract`. Relative filesystem inputs
(`contracts`, `output`, `platform`, and `templates`) resolve from the project root, not from the
configuration file's directory. Controller and Logic directories, OpenAPI and manifest paths are
safe relative paths below `output`.

`platform` names a trusted PHP file returning `PlatformAdapter`, `TemplatePack`, or
`TemplatePackBuilder`. A platform definition receives the complete resolved option map as `$options`.
`adapter` selects a bundled named platform and remains `plato` by default.

The PlatoPHP console bridge continues to read the `api_contract` section of project-root
`plato.config.php`:

```php
$root = __DIR__;

return [
    'app_path' => $root . '/app',
    'commands' => [
        Lumnd\PlatoApiContract\Platform\Plato\Console\ApiLintCommand::class,
        Lumnd\PlatoApiContract\Platform\Plato\Console\ApiGenerateCommand::class,
        Lumnd\PlatoApiContract\Platform\Plato\Console\ApiCheckCommand::class,
    ],
    'api_contract' => [
        'contracts' => $root . '/api/contracts',
        'output' => $root,
        'controller-namespace' => 'api\\control',
        'logic-namespace' => 'api\\logic',
        'controller-dir' => 'api/app/control',
        'logic-dir' => 'api/app/logic',
        'templates' => $root . '/api/templates',
        'openapi' => 'docs/api/openapi.json',
        'manifest' => 'api/manifest.json',
        'title' => 'Project API',
        'api-version' => '1.0.0',
        'exception' => common\exception\biz_exception::class,
    ],
];
```

## How a refused request is answered

`exception` names the class a generated action refuses invalid input with. It must implement
`Lumnd\PlatoApiContract\Runtime\Refusal`, one static `refuse(array $errors)` returning the throwable
to raise, and the action becomes:

```php
if ($validator->fails()) {
    throw \common\exception\biz_exception::refuse($validator->errors());
}
```

An application with one failure mechanism and one middleware rendering it therefore answers a bad
request the same way it answers a business rule that said no, and this package states no envelope
for failures at all. The class is checked before anything is generated, so a name that cannot serve
is a configuration error rather than a fatal in a controller.

Registering nothing keeps the built-in answer, `422` with the field errors, which needs no
application code:

```php
if ($validator->fails()) {
    return resp::json(['errors' => $validator->errors()], 422);
}
```

Changing `exception` changes every generated action, so it is part of the generation fingerprint:
registering one, or dropping it, puts the generated controllers out of date.

## Plato strategy classes

`strategies` replaces one piece of the bundled Plato controller generator while retaining the other
defaults:

```php
'api_contract' => [
    'strategies' => [
        'response-writer' => app\api\ContractResponseWriter::class,
    ],
],
```

| Role | Required interface |
| --- | --- |
| `request-source` | `Platform\Plato\RequestSource` |
| `validation-rules` | `Platform\Plato\ValidationRules` |
| `dto-hydration` | `Platform\Plato\DtoHydration` |
| `logic-resolver` | `Platform\Plato\LogicResolver` |
| `response-writer` | `Platform\Plato\ResponseWriter` |

Interface names above are below the `Lumnd\PlatoApiContract` namespace. Each configured class must
exist in the host Composer autoloader, implement the matching interface, be instantiable and have no
required constructor arguments. The generator validates all strategy classes before rendering any
artifact. These roles configure the bundled `plato` adapter; a complete Template Pack owns its own
helpers and behavior instead.

## Ownership, checks and per-run flags

The manifest is deterministic and should be committed. It records every artifact and the SHA-256 of
generator-owned files. The built-in Plato adapter treats controllers and OpenAPI as generated, and
Logic skeletons as user-owned:

| Ownership | Later generation |
| --- | --- |
| generated | updates when unchanged since generation; removes when no longer produced |
| user | creates only when absent; never reads, replaces or removes it |
| tool | rewrites the manifest itself; the manifest does not record its own hash |

An unexplained edit to a generated file makes `api:generate` exit `3` without writing anything.
Move the change into Logic or a template. `--force` explicitly discards edits to generated files,
including edited obsolete files, but it never changes user-owned Logic. `--dry-run` prints the plan
and performs no writes. These two flags are decisions about one generation run and are not accepted
in a configuration file.

`api:check` uses the same configuration and writes nothing. It exits `4` and reports `missing`,
`stale`, `modified` or `obsolete` when disk does not match the contracts. It must receive exactly the
same project options as generation; keeping those values in one configuration file prevents drift.

| Exit | Meaning |
| ---: | --- |
| `0` | success/current |
| `2` | contract error |
| `3` | generation or configuration conflict |
| `4` | stale generated artifacts from `api:check` |
| `70` | internal error |

Generated Plato controllers and Logic signatures import this package's runtime classes. The host
must keep `lumnd/plato-api-contract` in Composer `require` so generated code still runs after
`composer install --no-dev`.
