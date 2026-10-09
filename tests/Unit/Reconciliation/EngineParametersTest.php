<?php

namespace Tests\Unit\Reconciliation;

use App\Services\Reconciliation\Matching\EngineParameters;
use PHPUnit\Framework\TestCase;

class EngineParametersTest extends TestCase
{
    protected function parameters(int $toleranceCents = 50, ?int $toleranceBasisPoints = null, int $surchargeCapBasisPoints = 1000): EngineParameters
    {
        return new EngineParameters(
            toleranceCents: $toleranceCents,
            toleranceBasisPoints: $toleranceBasisPoints,
            surchargeCapBasisPoints: $surchargeCapBasisPoints,
        );
    }

    public function test_fixed_tolerance_applies_to_any_balance(): void
    {
        $parameters = $this->parameters(50);

        $this->assertSame(50, $parameters->toleranceFor(100));
        $this->assertSame(50, $parameters->toleranceFor(10000000));
    }

    public function test_percentage_tolerance_follows_the_balance(): void
    {
        $parameters = $this->parameters(0, 100);

        $this->assertSame(1000, $parameters->toleranceFor(100000));
        $this->assertSame(1, $parameters->toleranceFor(199));
        $this->assertSame(0, $parameters->toleranceFor(99));
    }

    public function test_with_both_modes_the_larger_one_applies(): void
    {
        $parameters = $this->parameters(50, 100);

        $this->assertSame(1000, $parameters->toleranceFor(100000));
        $this->assertSame(50, $parameters->toleranceFor(1000));
    }

    public function test_percentage_tolerance_stops_at_its_cap(): void
    {
        $parameters = new EngineParameters(toleranceCents: 50, toleranceBasisPoints: 100, toleranceCapCents: 20000);

        $this->assertSame(50, $parameters->toleranceFor(4000));
        $this->assertSame(1000, $parameters->toleranceFor(100000));
        $this->assertSame(20000, $parameters->toleranceFor(2000000));
        $this->assertSame(20000, $parameters->toleranceFor(5000000));
    }

    public function test_a_cap_below_the_fixed_tolerance_does_not_lower_it(): void
    {
        $this->assertSame(50, (new EngineParameters(toleranceCents: 50, toleranceBasisPoints: 100, toleranceCapCents: 10))->toleranceFor(5000000));
    }

    public function test_zero_tolerance_accepts_no_difference(): void
    {
        $this->assertSame(0, $this->parameters(0)->toleranceFor(100000));
    }

    public function test_surcharge_is_allowed_up_to_the_cap(): void
    {
        $parameters = $this->parameters(surchargeCapBasisPoints: 1000);

        $this->assertTrue($parameters->allowsSurcharge(9999, 100000));
        $this->assertTrue($parameters->allowsSurcharge(10000, 100000));
        $this->assertFalse($parameters->allowsSurcharge(10001, 100000));
        $this->assertFalse($parameters->allowsSurcharge(0, 100000));
    }

    public function test_zero_cap_allows_no_surcharge(): void
    {
        $this->assertFalse($this->parameters(surchargeCapBasisPoints: 0)->allowsSurcharge(1, 100000));
    }

    public function test_defaults_match_the_initial_configuration(): void
    {
        $parameters = $this->parameters();

        $this->assertSame(90, $parameters->automaticThreshold);
        $this->assertSame(60, $parameters->suggestionThreshold);
        $this->assertSame(90, $parameters->supplierThreshold);
        $this->assertSame(3, $parameters->lookbackMonths);
        $this->assertSame(5, $parameters->suggestionsPerAuthorization);
    }
}
