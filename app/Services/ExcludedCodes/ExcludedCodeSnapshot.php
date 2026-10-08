<?php

namespace App\Services\ExcludedCodes;

final readonly class ExcludedCodeSnapshot
{
    /**
     * @var array<string, true>
     */
    private array $lookup;

    /**
     * @var list<string>
     */
    private array $codes;

    /**
     * @param  iterable<int, string>  $codes
     */
    public function __construct(iterable $codes)
    {
        $lookup = [];
        $list = [];

        foreach ($codes as $code) {
            $lookup['#'.$code] = true;
            $list[] = (string) $code;
        }

        sort($list, SORT_STRING);

        $this->lookup = $lookup;
        $this->codes = $list;
    }

    /**
     * Whether a payment's operation code is in the list: exact match, ignoring surrounding spaces.
     */
    public function contains(?string $operationCode): bool
    {
        $code = OperationCode::normalize($operationCode);

        return $code !== null && isset($this->lookup['#'.$code]);
    }

    /**
     * @return list<string> The codes in ascending order
     */
    public function codes(): array
    {
        return $this->codes;
    }
}
