<?php

namespace App\Services\ExcludedCodes;

final readonly class ParsedExcludedCodeFile
{
    /**
     * @param  list<array{code: string, description: string|null}>  $codes  Valid codes, each one once
     * @param  list<array{row: int, column: string, reason: string}>  $errors
     * @param  int  $repeated  Occurrences of a code beyond its first one in the file
     * @param  string|null  $refusal  Reason the file is refused as a whole
     */
    public function __construct(
        public array $codes = [],
        public array $errors = [],
        public int $repeated = 0,
        public ?string $refusal = null,
    ) {}

    public static function refused(string $reason): self
    {
        return new self(refusal: $reason);
    }

    public function isAccepted(): bool
    {
        return $this->errors === [] && $this->refusal === null;
    }
}
