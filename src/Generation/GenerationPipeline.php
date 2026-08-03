<?php

declare(strict_types=1);

namespace Lumnd\PlatoApiContract\Generation;

use JsonException;
use Lumnd\PlatoApiContract\Exception\GenerationException;
use Lumnd\PlatoApiContract\Exception\TemplateException;
use Lumnd\PlatoApiContract\Ir\ContractCollection;
use Lumnd\PlatoApiContract\OpenApi\DocumentOptions;
use Lumnd\PlatoApiContract\OpenApi\OpenApiGenerator;
use Lumnd\PlatoApiContract\OpenApi\OpenApiValidator;

/**
 * Contracts in, verified files out.
 *
 * The order is fixed: the adapter produces platform files, the pipeline produces the shared OpenAPI
 * document and the manifest, everything is verified, ownership is checked against what the project
 * already holds, and only then is anything written. A failure anywhere before the write step leaves
 * the project untouched instead of half generated.
 */
final class GenerationPipeline
{
    public function __construct(
        private readonly PlatformRegistry $platforms,
        private readonly OpenApiGenerator $openApi = new OpenApiGenerator(),
        private readonly OpenApiValidator $openApiValidator = new OpenApiValidator(),
        private readonly ArtifactVerifier $verifier = new ArtifactVerifier(),
        private readonly ArtifactWriter $writer = new ArtifactWriter(),
        private readonly OwnershipInspector $ownership = new OwnershipInspector(),
    ) {
    }

    /**
     * Build and verify every artifact without touching the filesystem.
     *
     * @throws GenerationException
     * @throws TemplateException when a template is missing or fails to render
     */
    public function plan(
        ContractCollection $contracts,
        GenerationContext $context,
        string $platform,
    ): GenerationResult {
        $adapter = $this->platforms->get($platform);
        $artifacts = $adapter->generate($contracts, $context);
        $artifacts[] = $this->openApiArtifact($contracts, $context);

        $fingerprint = new GenerationFingerprint(
            contracts: hash('sha256', serialize($contracts)),
            config: $context->config->hash(),
            adapter: $adapter->fingerprint(),
            templates: $adapter->templateFingerprint(),
        );

        // The manifest records every other artifact, so it is built last and describes the project
        // exactly as this run would leave it.
        $artifacts[] = new GeneratedArtifact(
            $context->config->manifestPath,
            Manifest::fromArtifacts($adapter->name(), $fingerprint, $artifacts)->toJson(),
            Ownership::Tool,
        );

        $errors = $this->verifier->verify($artifacts);
        if ($errors !== []) {
            throw new GenerationException($errors);
        }

        return new GenerationResult($adapter->name(), $artifacts, $fingerprint);
    }

    /**
     * How this plan compares to the files the project already holds.
     *
     * @throws GenerationException when the recorded manifest exists and cannot be read
     */
    public function inspect(GenerationResult $result, GenerationContext $context): OwnershipReport
    {
        return $this->ownership->inspect($context->root, $this->recordedManifest($context), $result->artifacts);
    }

    /**
     * @param bool $force replace generated files whose contents this tool cannot account for
     * @throws GenerationException
     * @throws TemplateException when a template is missing or fails to render
     */
    public function run(
        ContractCollection $contracts,
        GenerationContext $context,
        string $platform,
        bool $force = false,
    ): GenerationResult {
        $result = $this->plan($contracts, $context, $platform);
        $recorded = $this->recordedManifest($context);
        $report = $this->ownership->inspect($context->root, $recorded, $result->artifacts);

        // Only files this run would otherwise write over stop it. A file it no longer generates at
        // all is reported, not treated as a reason to refuse the endpoints that are still declared.
        $edited = $report->withStatus(ArtifactStatus::Modified);
        if (!$force && $edited !== []) {
            throw new GenerationException(array_merge(
                array_map(
                    static fn (ArtifactState $state): string
                        => 'Edited by hand since it was generated: ' . $state->path,
                    $edited,
                ),
                ['Move the change into a template or into Logic, or re-run with --force to discard it.'],
            ));
        }

        $result = $this->withRetainedOrphans($result, $recorded, $report, $force);
        [$projectArtifacts, $toolArtifacts] = $this->writeBatches($result->artifacts);

        // Cleanup must succeed before any new output is published, and the manifest is the commit
        // record for the whole run, so it is written only after every other filesystem operation.
        $removed = $this->writer->remove($context->root, $this->removable($report, $force));
        $written = $this->writer->write($context->root, $projectArtifacts);
        $written = array_merge($written, $this->writer->write($context->root, $toolArtifacts));

        return $result->withChanges($written, $removed, $report);
    }

    /**
     * Keep reporting an edited obsolete file until the application removes it or explicitly forces
     * generation to discard it. Its original recorded hash must survive: recording the edited bytes
     * would incorrectly make the file removable on the next run.
     */
    private function withRetainedOrphans(
        GenerationResult $result,
        Manifest $recorded,
        OwnershipReport $report,
        bool $force,
    ): GenerationResult {
        if ($force) {
            return $result;
        }

        $retained = [];
        foreach ($report->withStatus(ArtifactStatus::OrphanedModified) as $state) {
            $entry = $recorded->entry($state->path);
            if ($entry !== null) {
                $retained[$state->path] = $entry;
            }
        }
        if ($retained === []) {
            return $result;
        }

        $manifest = Manifest::fromArtifacts(
            $result->adapter,
            $result->fingerprint,
            $result->artifacts,
            $retained,
        )->toJson();
        $artifacts = array_map(
            static fn (GeneratedArtifact $artifact): GeneratedArtifact => $artifact->ownership === Ownership::Tool
                ? new GeneratedArtifact($artifact->path, $manifest, Ownership::Tool)
                : $artifact,
            $result->artifacts,
        );

        return new GenerationResult($result->adapter, $artifacts, $result->fingerprint);
    }

    /**
     * @param list<GeneratedArtifact> $artifacts
     * @return array{0: list<GeneratedArtifact>, 1: list<GeneratedArtifact>}
     */
    private function writeBatches(array $artifacts): array
    {
        $project = [];
        $tool = [];
        foreach ($artifacts as $artifact) {
            if ($artifact->ownership === Ownership::Tool) {
                $tool[] = $artifact;
                continue;
            }
            $project[] = $artifact;
        }

        return [$project, $tool];
    }

    /**
     * @throws GenerationException
     */
    private function recordedManifest(GenerationContext $context): Manifest
    {
        return Manifest::fromFile(
            rtrim($context->root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR
                . str_replace('/', DIRECTORY_SEPARATOR, $context->config->manifestPath),
        );
    }

    /**
     * @return list<string>
     */
    private function removable(OwnershipReport $report, bool $force): array
    {
        $paths = $report->removable();
        if (!$force) {
            return $paths;
        }

        foreach ($report->withStatus(ArtifactStatus::OrphanedModified) as $state) {
            $paths[] = $state->path;
        }

        return $paths;
    }

    /**
     * @throws GenerationException
     */
    private function openApiArtifact(ContractCollection $contracts, GenerationContext $context): GeneratedArtifact
    {
        $options = DocumentOptions::fromConfig($context->config);
        $document = $this->openApi->collectionDocument($contracts, $options);

        $errors = $this->openApiValidator->validate($document);
        if ($errors !== []) {
            throw new GenerationException($errors);
        }

        try {
            $json = $this->openApi->collectionJson($contracts, $options);
        } catch (JsonException $exception) {
            throw new GenerationException(['Unable to encode the OpenAPI document: ' . $exception->getMessage()]);
        }

        return new GeneratedArtifact($context->config->openApiPath, $json, Ownership::Generated);
    }
}
