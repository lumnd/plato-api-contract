<?php

declare(strict_types=1);

namespace Lumnd\PlatoApiContract\Contract;

/**
 * One node of the tree a rule set's dot paths describe.
 *
 * `user.name` and `tags.*` are flat text in the contract; the compiler needs them as a tree before
 * it can say what `user` is, whether `tags` has elements, or which properties an object requires.
 */
final class RuleNode
{
    /** @var array<string, self> named properties, in declaration order */
    public array $children = [];

    /** The `*` element of an array, when the contract declared one. */
    public ?self $items = null;

    /** What the contract said about this node itself, null when only its descendants were named. */
    public ?FieldRules $rules = null;

    public function __construct(public readonly string $path)
    {
    }

    public function child(string $name, string $path): self
    {
        return $this->children[$name] ??= new self($path);
    }

    public function element(string $path): self
    {
        return $this->items ??= new self($path);
    }

    /**
     * The type the node's shape forces, which outranks anything its own rules named.
     */
    public function structuralType(): ?string
    {
        if ($this->items !== null) {
            return 'array';
        }

        return $this->children === [] ? null : 'object';
    }

    /** Whether this node, or anything below it, is a required leaf. */
    public function holdsRequired(): bool
    {
        if ($this->rules?->presence === 'required') {
            return true;
        }
        foreach ($this->children as $child) {
            if ($child->holdsRequired()) {
                return true;
            }
        }

        return $this->items?->holdsRequired() ?? false;
    }
}
