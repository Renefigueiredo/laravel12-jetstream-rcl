<?php

namespace Tests\Unit\Conciliation;

use App\Services\ExcludedCodes\OperationCode;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class OperationCodeTest extends TestCase
{
    public function test_normalize_removes_surrounding_spaces(): void
    {
        $this->assertSame('20150652', OperationCode::normalize(' 20150652 '));
        $this->assertSame('20150652', OperationCode::normalize("\u{00A0}20150652\u{00A0}"));
        $this->assertSame('20150652', OperationCode::normalize("\t20150652\r\n"));
    }

    public function test_normalize_returns_null_when_nothing_is_left(): void
    {
        $this->assertNull(OperationCode::normalize(null));
        $this->assertNull(OperationCode::normalize(''));
        $this->assertNull(OperationCode::normalize('   '));
    }

    public function test_normalize_preserves_case_and_leading_zeros(): void
    {
        $this->assertSame('00123', OperationCode::normalize('00123'));
        $this->assertSame('AbC12', OperationCode::normalize(' AbC12 '));
    }

    #[DataProvider('validCodes')]
    public function test_valid_codes_are_accepted(string $code): void
    {
        $this->assertTrue(OperationCode::isValid($code));
    }

    #[DataProvider('invalidCodes')]
    public function test_invalid_codes_are_refused(string $code): void
    {
        $this->assertFalse(OperationCode::isValid($code));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function validCodes(): array
    {
        return [
            'one character' => ['7'],
            'digits' => ['20150652'],
            'letters and digits' => ['AB12cd'],
            'leading zeros' => ['00123'],
            'twenty characters' => [str_repeat('9', 20)],
        ];
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidCodes(): array
    {
        return [
            'empty' => [''],
            'twenty-one characters' => [str_repeat('9', 21)],
            'hyphen' => ['12-34'],
            'inner space' => ['12 34'],
            'accented letter' => ['CÓDIGO'],
            'dot' => ['12.34'],
            'trailing line break' => ["1234\n"],
        ];
    }
}
