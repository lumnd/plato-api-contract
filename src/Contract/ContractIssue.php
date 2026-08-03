<?php

declare(strict_types=1);

namespace Lumnd\PlatoApiContract\Contract;

final readonly class ContractIssue
{
    public function __construct(
        public string $code,
        public string $file,
        public string $path,
        public string $message,
    ) {
    }

    public function format(): string
    {
        return $this->file . ':' . $this->path . ' [' . $this->code . '] ' . $this->message;
    }

    /** @return array{code: string, file: string, path: string, message: string} */
    public function toArray(): array
    {
        return [
            'code' => $this->code,
            'file' => $this->file,
            'path' => $this->path,
            'message' => $this->message,
        ];
    }
}
