<?php

declare(strict_types=1);

namespace Lumnd\PlatoApiContract\Php;

use Lumnd\PlatoApiContract\Ir\Field;
use Lumnd\PlatoApiContract\Ir\Schema;

/** Maps framework-neutral contract schemas to PHP and PHPStan type spelling. */
final class PhpTypes
{
    public function field(Field $field): string
    {
        return $this->schema($field->schema ?? new Schema($field->type, nullable: $field->nullable));
    }

    public function schema(Schema $schema): string
    {
        $type = match ($schema->type) {
            'integer' => 'int',
            'number' => 'float',
            'boolean' => 'bool',
            'array' => 'array',
            'object' => 'array',
            'null' => 'null',
            default => 'string',
        };

        return $schema->nullable && $type !== 'null' ? '?' . $type : $type;
    }

    public function phpstan(Schema $schema): string
    {
        $type = match ($schema->type) {
            'integer' => 'int',
            'number' => 'float',
            'boolean' => 'bool',
            'array' => 'list<' . $this->phpstan($schema->items ?? new Schema('string')) . '>',
            'object' => $this->shape($schema),
            'null' => 'null',
            default => 'string',
        };

        return $schema->nullable && $type !== 'null' ? $type . '|null' : $type;
    }

    private function shape(Schema $schema): string
    {
        $fields = [];
        foreach ($schema->properties as $name => $property) {
            $required = in_array($name, $schema->requiredProperties, true);
            $fields[] = $name . ($required ? '' : '?') . ': ' . $this->phpstan($property);
        }

        return $fields === [] ? 'array{}' : 'array{' . implode(', ', $fields) . '}';
    }
}
