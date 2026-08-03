<?php

declare(strict_types=1);

use Lumnd\PlatoApiContract\Generation\GenerationConfig;
use Lumnd\PlatoApiContract\Generation\GenerationContext;
use Lumnd\PlatoApiContract\Generation\GenerationPipeline;
use Lumnd\PlatoApiContract\Generation\PlatformRegistry;
use Lumnd\PlatoApiContract\Platform\Plato\PlatoConfig;
use Lumnd\PlatoApiContract\Platform\Plato\PlatoPlatformAdapter;

/** @var resource|null $fixtureProcess */
$fixtureProcess = null;
$fixturePort = 0;

beforeAll(function () use (&$fixtureProcess, &$fixturePort): void {
    $root = dirname(__DIR__) . '/Fixtures/project';
    $adapter = new PlatoPlatformAdapter(new PlatoConfig(
        'Fixture\\control',
        'Fixture\\logic',
    ));

    // The generated controller is written into the fixture application; its hand-written Logic file
    // is never overwritten, so a real request reaches real user code.
    (new GenerationPipeline(new PlatformRegistry([$adapter])))->run(
        fixture_contracts('http'),
        new GenerationContext($root, new GenerationConfig(openApiPath: 'docs/openapi.json')),
        PlatoPlatformAdapter::NAME,
    );

    $socket = stream_socket_server('tcp://127.0.0.1:0', $errorNumber, $errorMessage);
    if ($socket === false) {
        throw new RuntimeException($errorMessage ?? 'Unable to allocate a local port.', $errorNumber ?? 0);
    }
    $address = stream_socket_get_name($socket, false);
    fclose($socket);
    $fixturePort = (int) substr((string) $address, strrpos((string) $address, ':') + 1);

    $command = [PHP_BINARY, '-S', '127.0.0.1:' . $fixturePort, $root . '/server.php'];
    $fixtureProcess = proc_open($command, [STDIN, ['file', '/dev/null', 'a'], ['file', '/dev/null', 'a']], $pipes, $root);
    if (!is_resource($fixtureProcess)) {
        throw new RuntimeException('Unable to start the fixture HTTP server.');
    }

    $deadline = microtime(true) + 5;
    while (microtime(true) < $deadline) {
        $connection = @fsockopen('127.0.0.1', $fixturePort, $errorNumber, $errorMessage, 0.1);
        if ($connection !== false) {
            fclose($connection);
            return;
        }
        usleep(50000);
    }

    throw new RuntimeException('Fixture HTTP server did not start.');
});

afterAll(function () use (&$fixtureProcess): void {
    if (is_resource($fixtureProcess)) {
        proc_terminate($fixtureProcess);
        proc_close($fixtureProcess);
    }
});

it('routes a real request through the generated controller into user owned Logic', function () use (&$fixturePort) {
    $body = file_get_contents('http://127.0.0.1:' . $fixturePort . '/ping/index?message=hello');

    /** @var array<string, mixed> $decoded */
    $decoded = json_decode((string) $body, true, flags: JSON_THROW_ON_ERROR);

    // The answer is plato's own resp::response(), which stamps it as well: the contract names the
    // three fields, and `timestamp` is what that helper adds to every one of them
    expect($body)->not->toBeFalse()
        ->and(array_diff_key($decoded, ['timestamp' => null]))
        ->toBe([
            'code' => 0,
            'msg' => 'successful',
            'data' => ['message' => 'pong:hello'],
        ])
        ->and($decoded['timestamp'])->toBeInt();
});

it('returns a validation response before calling logic', function () use (&$fixturePort) {
    $context = stream_context_create(['http' => ['ignore_errors' => true]]);
    $body = file_get_contents('http://127.0.0.1:' . $fixturePort . '/ping/index', false, $context);
    $response = json_decode((string) $body, true, flags: JSON_THROW_ON_ERROR);

    expect($body)->not->toBeFalse()
        ->and($response)->toHaveKey('errors.message');
});

/**
 * @param array<string, string> $headers
 * @return array{status: int, body: array<string, mixed>}
 */
function fixture_request(int $port, string $path, array $headers = []): array
{
    $header = '';
    foreach ($headers as $name => $value) {
        $header .= $name . ': ' . $value . "\r\n";
    }

    $body = file_get_contents(
        'http://127.0.0.1:' . $port . $path,
        false,
        stream_context_create(['http' => ['ignore_errors' => true, 'header' => $header]]),
    );

    // The http stream wrapper declares $http_response_header in this scope for every response.
    $status = 0;
    foreach ($http_response_header as $line) {
        if (preg_match('#^HTTP/\S+ (\d{3})#', $line, $match) === 1) {
            $status = (int) $match[1];
        }
    }

    /** @var array<string, mixed> $decoded */
    $decoded = json_decode((string) $body, true, flags: JSON_THROW_ON_ERROR);

    return ['status' => $status, 'body' => $decoded];
}

/**
 * @param array<string, mixed> $body
 * @return array{status: int, body: array<string, mixed>}
 */
function fixture_post(int $port, string $path, array $body): array
{
    $payload = json_encode($body, JSON_THROW_ON_ERROR);
    $response = file_get_contents(
        'http://127.0.0.1:' . $port . $path,
        false,
        stream_context_create(['http' => [
            'ignore_errors' => true,
            'method' => 'POST',
            'header' => "Content-Type: application/json\r\n",
            'content' => $payload,
        ]]),
    );

    $status = 0;
    foreach ($http_response_header as $line) {
        if (preg_match('#^HTTP/\S+ (\d{3})#', $line, $match) === 1) {
            $status = (int) $match[1];
        }
    }

    /** @var array<string, mixed> $decoded */
    $decoded = json_decode((string) $response, true, flags: JSON_THROW_ON_ERROR);

    return ['status' => $status, 'body' => $decoded];
}

it('runs the rules of a field inside an array element against a real request', function () use (&$fixturePort) {
    $accepted = fixture_post($fixturePort, '/ping/save_items', [
        'items' => [['sku' => 'a1'], ['sku' => 'b2', 'qty' => 3, 'note' => 'gift']],
        'buyer' => ['nick' => 'jam'],
    ]);
    $missing = fixture_post($fixturePort, '/ping/save_items', [
        'items' => [['sku' => 'a1'], ['qty' => 3]],
    ]);
    $tooLong = fixture_post($fixturePort, '/ping/save_items', [
        'items' => [['sku' => 'far too long a sku']],
    ]);

    expect($accepted['status'])->toBe(200)
        // The declared default of an element's field reaches Logic, exactly as a top level one does.
        ->and($accepted['body']['data'])
        ->toBe(['count' => 2, 'first_sku' => 'a1', 'total' => 4, 'buyer_nick' => 'jam'])
        ->and($missing['status'])->toBe(422)
        ->and($missing['body'])->toHaveKey('errors.items[1][sku]')
        ->and($tooLong['status'])->toBe(422)
        ->and($tooLong['body'])->toHaveKey('errors.items[0][sku]');
});

it('refuses a real request whose containers are not the declared ones', function () use (&$fixturePort) {
    $notAList = fixture_post($fixturePort, '/ping/save_items', ['items' => 'nope']);
    $keyed = fixture_post($fixturePort, '/ping/save_items', ['items' => ['first' => ['sku' => 'a1']]]);
    $notOneValue = fixture_post($fixturePort, '/ping/save_items', ['items' => [['sku' => ['a1']]]]);
    // A scalar field that arrived as a list: every rule of it would otherwise be run against the
    // elements, pass, and leave Logic with ''.
    $listedScalar = fixture_request($fixturePort, '/ping/echo_message?message[]=hello');
    // `buyer` demands nothing of the caller, so nothing below it is asked and the object itself is
    // all there is to ask: this used to be accepted and handed to Logic as [].
    $notAnObject = fixture_post($fixturePort, '/ping/save_items', [
        'items' => [['sku' => 'a1']],
        'buyer' => 'nope',
    ]);
    $listedObject = fixture_post($fixturePort, '/ping/save_items', [
        'items' => [['sku' => 'a1']],
        'buyer' => ['nope'],
    ]);
    // `{}` and `[]` are the same array by the time a rule runs, so an empty object stays acceptable.
    $emptyObject = fixture_post($fixturePort, '/ping/save_items', [
        'items' => [['sku' => 'a1']],
        'buyer' => [],
    ]);
    $listedElement = fixture_post($fixturePort, '/ping/save_items', ['items' => ['nope']]);

    expect($notAList['status'])->toBe(422)
        ->and($notAList['body'])->toHaveKey('errors.items')
        ->and($keyed['status'])->toBe(422)
        ->and($keyed['body'])->toHaveKey('errors.items')
        ->and($notOneValue['status'])->toBe(422)
        ->and($notOneValue['body'])->toHaveKey('errors.items[0][sku]')
        ->and($listedScalar['status'])->toBe(422)
        ->and($listedScalar['body'])->toHaveKey('errors.message')
        ->and($notAnObject['status'])->toBe(422)
        ->and($notAnObject['body'])->toHaveKey('errors.buyer')
        ->and($listedObject['status'])->toBe(422)
        ->and($listedObject['body'])->toHaveKey('errors.buyer')
        ->and($emptyObject['status'])->toBe(200)
        ->and($emptyObject['body']['data']['buyer_nick'])->toBeNull()
        ->and($listedElement['status'])->toBe(422)
        ->and($listedElement['body'])->toHaveKey('errors.items[0]');
});

it('refuses a protected operation that arrives without an identity', function () use (&$fixturePort) {
    $result = fixture_request($fixturePort, '/ping/whoami?message=hello');

    expect($result['status'])->toBe(401)
        ->and($result['body'])->toBe(['code' => 401, 'msg' => 'Unauthorized', 'data' => null]);
});

it('lets an authenticated request through to a protected operation', function () use (&$fixturePort) {
    $result = fixture_request($fixturePort, '/ping/whoami?message=hello', ['X-Fixture-User' => 'james']);

    expect($result['status'])->toBe(200)
        ->and($result['body'])->toMatchArray(['code' => 0, 'msg' => 'successful', 'data' => ['message' => 'hello:james']]);
});

it('uses the same authentication requirement for every private operation', function () use (&$fixturePort) {
    $result = fixture_request($fixturePort, '/ping/admin?message=hello', ['X-Fixture-User' => 'james']);

    expect($result['status'])->toBe(200)
        ->and($result['body'])->toMatchArray([
            'code' => 0,
            'msg' => 'successful',
            'data' => ['message' => 'hello:james:admin'],
        ]);
});

it('hands Logic every field a rule set declared, sent or not', function () use (&$fixturePort) {
    // Neither `loud` nor `note` carries a rule the validator can run, and neither is sent here.
    // The declared default and null still arrive, which is what the projection is for.
    $result = fixture_request($fixturePort, '/ping/echo_message?message=hello');

    expect($result['status'])->toBe(200)
        ->and($result['body'])->toMatchArray([
            'code' => 0,
            'msg' => 'successful',
            'data' => ['message' => 'hello', 'note' => null],
        ]);
});

it('carries an optional field that has no rule through to Logic when it is sent', function () use (&$fixturePort) {
    $result = fixture_request($fixturePort, '/ping/echo_message?message=hello&loud=1&note=seen');

    expect($result['status'])->toBe(200)
        ->and($result['body']['data'])->toBe(['message' => 'HELLO', 'note' => 'seen']);
});

it('refuses a boolean nobody spelled, rather than reading it as false', function () use (&$fixturePort) {
    $result = fixture_request($fixturePort, '/ping/echo_message?message=hello&loud=fasle');

    expect($result['status'])->toBe(422)
        ->and($result['body'])->toHaveKey('errors.loud');
});

it('still refuses input the rule set rejects', function () use (&$fixturePort) {
    $result = fixture_request($fixturePort, '/ping/echo_message');

    expect($result['status'])->toBe(422)
        ->and($result['body'])->toHaveKey('errors.message');
});

it('authenticates before validation, so a denied request never reveals input rules', function () use (&$fixturePort) {
    // No message parameter: validation would answer 422, route authentication answers first.
    $result = fixture_request($fixturePort, '/ping/whoami');

    expect($result['status'])->toBe(401)
        ->and($result['body'])->not->toHaveKey('errors');
});
