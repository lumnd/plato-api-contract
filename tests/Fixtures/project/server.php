<?php

declare(strict_types=1);

use Fixture\auth\identity;
use plato\http\reply;
use plato\http\resp;
use plato\plato;

require dirname(__DIR__, 3) . '/vendor/autoload.php';

/**
 * The application resolves an identity or answers 401; the contract only declares whether an
 * operation requires authentication.
 */
$authenticate = static function (string $ct, string $ac): identity|reply {
    $name = (string) ($_SERVER['HTTP_X_FIXTURE_USER'] ?? '');
    if ($name === '') {
        return resp::json(['code' => 401, 'msg' => 'Unauthorized', 'data' => null], 401);
    }

    return new identity($name);
};

$root = __DIR__;
plato::registry([
    'app_path' => $root . '/app',
    'data_path' => sys_get_temp_dir() . '/plato-api-contract-fixture-' . getmypid(),
    'env_path' => $root . '/app/.env.testing',
    'debug' => false,
    'controller_namespace' => 'Fixture\\control',
    'check_purview_handle' => $authenticate,
]);

plato::run();
