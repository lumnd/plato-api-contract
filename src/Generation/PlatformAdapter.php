<?php

declare(strict_types=1);

namespace Lumnd\PlatoApiContract\Generation;

use Lumnd\PlatoApiContract\Contract\RouteConvention;
use Lumnd\PlatoApiContract\Exception\GenerationException;
use Lumnd\PlatoApiContract\Ir\ContractCollection;

/**
 * Turns framework-neutral contracts into the files one platform needs.
 *
 * An adapter owns routing constraints, request sources, validation rules, DTO handling, context and
 * response shape. It never parses the DSL and never produces the OpenAPI document: both are shared
 * so that two adapters can never document different APIs from the same contracts.
 */
interface PlatformAdapter
{
    public function name(): string;

    /**
     * The routing rules this platform can serve, so contracts are compiled against the same
     * convention that will later generate them.
     */
    public function routeConvention(): RouteConvention;

    /**
     * A hash that changes whenever this adapter would produce different output for identical
     * contracts: its identity and its configuration.
     */
    public function fingerprint(): string;

    /**
     * A hash of the templates this adapter renders, or an empty string when it renders none.
     *
     * It is separate from {@see fingerprint()} so that the manifest can tell a project "your own
     * template changed" apart from "the generator configuration changed".
     */
    public function templateFingerprint(): string;

    /**
     * @return list<GeneratedArtifact>
     * @throws GenerationException when the contracts use a capability this platform cannot serve
     */
    public function generate(ContractCollection $contracts, GenerationContext $context): array;
}
