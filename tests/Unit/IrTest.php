<?php

declare(strict_types=1);

use Lumnd\PlatoApiContract\Ir\Field;

it('rejects structurally invalid fields before they enter the IR', function () {
    expect(fn () => new Field('display-name', 'query', 'string'))->toThrow(InvalidArgumentException::class)
        ->and(fn () => new Field('name', 'unknown', 'string'))->toThrow(InvalidArgumentException::class)
        ->and(fn () => new Field('name', 'query', 'mystery'))->toThrow(InvalidArgumentException::class)
        ->and(fn () => new Field('name', 'query', 'string', required: true, default: 'x'))
        ->toThrow(InvalidArgumentException::class);
});

it('keeps optional and nullable as separate decisions', function () {
    expect((new Field('limit', 'query', 'integer'))->required)->toBeFalse()
        ->and((new Field('limit', 'query', 'integer', default: 20, hasDefault: true))->nullable)->toBeFalse()
        ->and((new Field('cursor', 'query', 'string', nullable: true))->nullable)->toBeTrue();
});
