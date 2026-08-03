<?php

declare(strict_types=1);

use Lumnd\PlatoApiContract\Runtime\Input;
use plato\security\validate;

/**
 * The rules a controller declares, run against real input the way the controller runs them.
 *
 * @param array<string, list<string>> $rules
 * @param array<string, mixed> $data
 * @return array<string, string>
 */
function validation_errors(array $rules, array $data): array
{
    return validate::make($data, $rules)->errors();
}

it('names every element an array carries, so a rule inside one is run', function () {
    $rules = [
        'items' => ['list', 'required'],
        'items[*][sku]' => ['scalar', 'required', 'maxlength[8]'],
        'items[*][qty]' => ['scalar', 'integer'],
    ];

    expect(validation_errors($rules, ['items' => [['sku' => 'a1', 'qty' => 2]]]))->toBe([])
        ->and(array_keys(validation_errors($rules, ['items' => [['qty' => 2]]])))
        ->toBe(['items[0][sku]'])
        ->and(array_keys(validation_errors($rules, ['items' => [['sku' => 'a'], ['sku' => 'b', 'qty' => 'x']]])))
        ->toBe(['items[1][qty]'])
        ->and(array_keys(validation_errors($rules, ['items' => [['sku' => 'far too long a sku']]])))
        ->toBe(['items[0][sku]']);
});

it('asks a required array for the array, not for every leaf below it', function () {
    $rules = ['items' => ['list', 'required'], 'items[*][sku]' => ['scalar', 'required']];

    expect(array_keys(validation_errors($rules, [])))->toBe(['items'])
        ->and(array_keys(validation_errors($rules, ['items' => []])))->toBe(['items'])
        // `validate` would have run `required` against every leaf, and demanded that an element's
        // optional property carry something.
        ->and(validation_errors($rules, ['items' => [['sku' => 'a', 'note' => '']]]))->toBe([]);
});

it('refuses a value whose container is not the one the contract declared', function () {
    $rules = [
        'name' => ['scalar', 'required'],
        'nick' => ['scalar'],
        'items' => ['list', 'required'],
        'items[*][sku]' => ['scalar', 'required'],
        'tags' => ['list'],
    ];
    // A scalar field holding a list would otherwise have its rules run against the elements, pass,
    // and be projected to ''.
    expect(array_keys(validation_errors($rules, ['name' => ['unexpected'], 'items' => [['sku' => 'a']]])))
        ->toBe(['name'])
        ->and(array_keys(validation_errors($rules, ['name' => 'a', 'nick' => ['x'], 'items' => [['sku' => 'a']]])))
        ->toBe(['nick'])
        ->and(array_keys(validation_errors($rules, ['name' => 'a', 'items' => [['sku' => ['x']]]])))
        ->toBe(['items[0][sku]'])
        // A list field holding anything else would have passed `required` and been projected to [].
        ->and(array_keys(validation_errors($rules, ['name' => 'a', 'items' => 'nope'])))
        ->toBe(['items'])
        // An associative array is renumbered by the projection, so `list<T>` would be a lie.
        ->and(array_keys(validation_errors($rules, ['name' => 'a', 'items' => ['first' => ['sku' => 'a']]])))
        ->toContain('items')
        ->and(array_keys(validation_errors($rules, ['name' => 'a', 'items' => [['sku' => 'a']], 'tags' => 'nope'])))
        ->toBe(['tags'])
        ->and(validation_errors($rules, ['name' => 'a', 'items' => [['sku' => 'a']], 'tags' => null]))
        ->toBe([]);
});

it('refuses a value that is not the object a field declared, which no property asks for', function () {
    // Every property optional, so nothing below the object is demanded of the caller and the object
    // itself is all there is to ask: without `map` this rule set accepts any value there is.
    $rules = ['buyer' => ['map'], 'buyer[name]' => ['scalar'], 'buyer[age]' => ['scalar', 'integer']];

    expect(validation_errors($rules, ['buyer' => ['name' => 'a', 'age' => 3]]))->toBe([])
        ->and(validation_errors($rules, []))->toBe([])
        ->and(validation_errors($rules, ['buyer' => null]))->toBe([])
        // A property walks down to a value that is not an array by reading it whole, so `age` is
        // told about the string as well. The object is the one that names what actually went wrong.
        ->and(array_keys(validation_errors($rules, ['buyer' => 'nope'])))->toBe(['buyer', 'buyer[age]'])
        ->and(array_keys(validation_errors($rules, ['buyer' => ['nope']])))->toBe(['buyer'])
        // `{}` and `[]` decode to the same empty array, so neither can be refused for the other.
        ->and(validation_errors($rules, ['buyer' => []]))->toBe([])
        // The container is settled first, and the properties are still checked after it.
        ->and(array_keys(validation_errors($rules, ['buyer' => ['name' => 'a', 'age' => 'x']])))
        ->toBe(['buyer[age]']);
});

it('asks for required children only after their optional parent object arrives', function () {
    $rules = ['buyer' => ['map'], 'buyer[name]' => ['scalar', 'required']];

    expect(validation_errors($rules, []))->toBe([])
        ->and(validation_errors($rules, ['buyer' => null]))->toBe([])
        ->and(array_keys(validation_errors($rules, ['buyer' => []])))->toBe(['buyer[name]'])
        ->and(validation_errors($rules, ['buyer' => ['name' => 'Ada']]))->toBe([]);
});

it('reports a missing required object without also reporting its absent children', function () {
    $rules = ['buyer' => ['map', 'required'], 'buyer[name]' => ['scalar', 'required']];

    expect(array_keys(validation_errors($rules, [])))->toBe(['buyer'])
        ->and(array_keys(validation_errors($rules, ['buyer' => null])))->toBe(['buyer'])
        ->and(array_keys(validation_errors($rules, ['buyer' => []])))->toBe(['buyer[name]']);
});

it('asks each element of an array of objects for the object, not only for its properties', function () {
    $rules = ['items' => ['list'], 'items[*]' => ['map'], 'items[*][note]' => ['scalar']];

    expect(validation_errors($rules, ['items' => [['note' => 'a'], []]]))->toBe([])
        ->and(array_keys(validation_errors($rules, ['items' => [['note' => 'a'], 'nope']])))
        ->toBe(['items[1]']);
});

it('reads a boolean the way the query string spells one, and refuses anything else', function () {
    $rules = ['loud' => ['scalar', 'regex_match[/^(1|0|true|false|on|off|yes|no)?$/i]']];

    expect(validation_errors($rules, ['loud' => true]))->toBe([])
        ->and(validation_errors($rules, ['loud' => false]))->toBe([])
        ->and(validation_errors($rules, ['loud' => 'YES']))->toBe([])
        ->and(validation_errors($rules, []))->toBe([])
        ->and(array_keys(validation_errors($rules, ['loud' => 'fasle'])))->toBe(['loud'])
        ->and(array_keys(validation_errors($rules, ['loud' => 2])))->toBe(['loud']);
});

it('does not read a value that spells no boolean as false', function () {
    expect(Input::flag('yes'))->toBeTrue()
        ->and(Input::flag('off'))->toBeFalse()
        ->and(Input::flag(false))->toBeFalse()
        ->and(Input::flag(null, true))->toBeTrue()
        // Neither true nor false was said, so the declared default stands rather than a guess.
        ->and(Input::flag('fasle', true))->toBeTrue()
        ->and(Input::flag('anything', null))->toBeNull();
});
