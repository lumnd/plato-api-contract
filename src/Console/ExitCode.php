<?php

declare(strict_types=1);

namespace Lumnd\PlatoApiContract\Console;

final class ExitCode
{
    public const SUCCESS = 0;
    public const CONTRACT_ERROR = 2;
    public const GENERATION_CONFLICT = 3;
    public const STALE_ARTIFACTS = 4;
    public const INTERNAL_ERROR = 70;

    private function __construct()
    {
    }
}
