<?php

namespace App\Services\Import;

final readonly class RowError
{
    public function __construct(
        public int $rowNumber,
        public string $column,
        public string $value,
        public string $reasonKey,
    ) {}

    public function reason(): string
    {
        return __($this->reasonKey);
    }

    /**
     * @return array{row: int, column: string, value: string, reason: string}
     */
    public function toArray(): array
    {
        return [
            'row' => $this->rowNumber,
            'column' => $this->column,
            'value' => $this->value,
            'reason' => $this->reason(),
        ];
    }
}
