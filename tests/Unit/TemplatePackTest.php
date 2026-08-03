<?php

declare(strict_types=1);

use Lumnd\PlatoApiContract\Contract\StandardRouteConvention;
use Lumnd\PlatoApiContract\Contract\ContractLoader;
use Lumnd\PlatoApiContract\Ir\PathTemplate;
use Lumnd\PlatoApiContract\Php\PhpImports;
use Lumnd\PlatoApiContract\Php\PhpTypes;

it('lets a standard route use an explicit handler independently of its URL shape', function () {
    $routes = new StandardRouteConvention();
    $path = PathTemplate::parse('/organizations/{organization}/users/{user}');

    expect($routes->violations('users', $path))->toBe([])
        ->and($routes->action('users', $path, 'show'))->toBe('show')
        ->and($routes->action('users', $path))->toBe('users');
});

it('compiles a REST contract action from its explicit handler', function () {
    $contracts = ContractLoader::forConvention(new StandardRouteConvention())
        ->loadDirectory(contract_fixture('rest'));
    $operation = $contracts->apis[0]->operations[0];

    expect($operation->action)->toBe('show')
        ->and($operation->path->value)->toBe('/organizations/{organization}/users/{user}')
        ->and($operation->path->parameters)->toBe(['organization', 'user']);
});

it('provides deterministic PHP helpers to template packs', function () {
    $imports = (new PhpImports())->add(['App\\Zed', '\\App\\Alpha', 'App\\Zed']);
    $field = ping_contract()->operations[0]->requestFields[0];

    expect($imports->all())->toBe(['App\\Alpha', 'App\\Zed'])
        ->and($imports->render())->toBe("use App\\Alpha;\nuse App\\Zed;\n")
        ->and((new PhpTypes())->field($field))->toBe('string');
});
