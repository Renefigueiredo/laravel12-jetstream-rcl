<?php

namespace App\Services\Import;

use Carbon\CarbonImmutable;

final readonly class ParsedRow
{
    /**
     * @param  array<string, mixed>  $attributes  Typed columns of the entry
     * @param  array<string, string|null>  $raw  The row as it came in the file
     */
    public function __construct(
        public int $rowNumber,
        public array $attributes,
        public string $identityKey,
        public CarbonImmutable $periodDate,
        public array $raw,
    ) {}
}
