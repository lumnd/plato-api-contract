<?php

declare(strict_types=1);

namespace Lumnd\PlatoApiContract\Contract;

use Lumnd\PlatoApiContract\Exception\PathTemplateException;
use Lumnd\PlatoApiContract\Ir\PathTemplate;

/**
 * Turns a declared path into a normalized {@see PathTemplate} with stable diagnostic codes.
 */
final class PathParser
{
    private const CODES = [
        PathTemplateException::SYNTAX => 'operation.path',
        PathTemplateException::PARAMETER_NAME => 'operation.path_parameter_name',
        PathTemplateException::PARAMETER_DUPLICATE => 'operation.path_segment_duplicate',
    ];

    /**
     * @param list<ContractIssue> $issues
     */
    public function parse(string $raw, string $file, string $dslPath, array &$issues): ?PathTemplate
    {
        try {
            return PathTemplate::parse($raw);
        } catch (PathTemplateException $exception) {
            $issues[] = new ContractIssue(
                self::CODES[$exception->reason],
                $file,
                $dslPath,
                $exception->getMessage(),
            );

            return null;
        }
    }

    /**
     * @param list<ContractIssue> $issues
     */
    public function checkConvention(
        RouteConvention $convention,
        string $controller,
        PathTemplate $path,
        string $file,
        string $dslPath,
        array &$issues,
    ): bool {
        $violations = $convention->violations($controller, $path);
        foreach ($violations as $violation) {
            $issues[] = new ContractIssue($violation['code'], $file, $dslPath, $violation['message']);
        }

        return $violations === [];
    }
}
