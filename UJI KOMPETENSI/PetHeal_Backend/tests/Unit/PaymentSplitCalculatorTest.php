<?php

namespace Tests\Unit;

use App\Services\PaymentSplitCalculator;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * Pembagian dana harus tepat sampai rupiah terakhir dan tidak pernah
 * mengarang merchant tujuan. Tes ini murni fungsi — tanpa database.
 */
class PaymentSplitCalculatorTest extends TestCase
{
    private PaymentSplitCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calculator = new PaymentSplitCalculator();
    }

    public function test_zero_commission_sends_everything_to_clinic(): void
    {
        $result = $this->calculator->calculate(100000, 0, 'CLINIC-1', null);

        $this->assertSame(100000, $result['clinic_amount']);
        $this->assertSame(0, $result['platform_amount']);
        $this->assertSame('fixed', $result['settings']['gross_amount_split']['type']);
        $this->assertSame('CLINIC-1', $result['settings']['gross_amount_split']['remaining_split_receiver']);
        $this->assertCount(1, $result['settings']['gross_amount_split']['split_rules']);
    }

    public function test_split_is_exact_with_half_up_rounding(): void
    {
        // 33.33% dari 1000 = 333.3 -> 333 (HALF_UP), sisa 667 ke klinik.
        $result = $this->calculator->calculate(1000, 33.33, 'CLINIC-1', 'PLATFORM-1');

        $this->assertSame(333, $result['platform_amount']);
        $this->assertSame(667, $result['clinic_amount']);
        $this->assertSame(1000, $result['platform_amount'] + $result['clinic_amount']);

        $rules = collect($result['settings']['gross_amount_split']['split_rules'])
            ->keyBy('merchant_id');
        $this->assertSame('667', $rules['CLINIC-1']['value']);
        $this->assertSame('333', $rules['PLATFORM-1']['value']);
    }

    public function test_rounding_never_loses_a_rupiah(): void
    {
        foreach ([1, 7, 99, 12345, 99999] as $gross) {
            foreach ([0.5, 1.25, 12.34, 50, 99.99] as $percent) {
                $result = $this->calculator->calculate($gross, $percent, 'CLINIC-1', 'PLATFORM-1');

                $this->assertSame(
                    $gross,
                    $result['platform_amount'] + $result['clinic_amount'],
                    sprintf('Gross %d / komisi %s tidak balance', $gross, $percent)
                );
                $this->assertGreaterThanOrEqual(0, $result['clinic_amount']);
            }
        }
    }

    public function test_commission_above_zero_without_platform_merchant_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->calculator->calculate(100000, 5, 'CLINIC-1', null);
    }

    public function test_missing_clinic_merchant_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->calculator->calculate(100000, 0, '   ', null);
    }

    public function test_invalid_amounts_and_percentages_are_rejected(): void
    {
        foreach ([[0, 0], [-1, 10], [1000, -1], [1000, 101]] as [$gross, $percent]) {
            try {
                $this->calculator->calculate($gross, $percent, 'CLINIC-1', 'PLATFORM-1');
                $this->fail(sprintf('Gross %d / komisi %s seharusnya ditolak', $gross, $percent));
            } catch (InvalidArgumentException $e) {
                $this->assertNotEmpty($e->getMessage());
            }
        }
    }
}
