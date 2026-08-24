<?php

namespace Tests\Unit;

use App\Support\Crm\TaxCalculator;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * 内税の金額計算の検証(DB を使わない)。
 *
 * 数字はすべて手計算と突き合わせている。
 */
class TaxCalculatorTest extends TestCase
{
    #[Test]
    public function the_tax_is_derived_from_the_tax_inclusive_amount_and_floored(): void
    {
        // 11,000 / 1.1 = 10,000 → 消費税 1,000(端数なし)
        $this->assertSame(1000, TaxCalculator::taxFromInclusive(11000, 10));

        // 1,000 / 1.1 = 909.09… → 消費税 90.90… → 切り捨てて 90
        $this->assertSame(90, TaxCalculator::taxFromInclusive(1000, 10));

        // 108 / 1.08 = 100 → 消費税 8(端数なし)
        $this->assertSame(8, TaxCalculator::taxFromInclusive(108, 8));

        // 100 / 1.08 = 92.59… → 消費税 7.40… → 切り捨てて 7
        $this->assertSame(7, TaxCalculator::taxFromInclusive(100, 8));

        // 端数しか出ない金額は 0 になる
        $this->assertSame(0, TaxCalculator::taxFromInclusive(10, 10));

        // 税率 0% と 0 円
        $this->assertSame(0, TaxCalculator::taxFromInclusive(10000, 0));
        $this->assertSame(0, TaxCalculator::taxFromInclusive(0, 10));
    }

    #[Test]
    public function the_tax_is_rounded_once_per_rate_group_not_per_line(): void
    {
        $summary = TaxCalculator::summarize([
            ['amount_incl_tax' => 1000, 'tax_rate_percent' => 10],
            ['amount_incl_tax' => 1000, 'tax_rate_percent' => 10],
        ]);

        // 税込 2,000 / 1.1 = 1,818.18… → 消費税 181.81… → 切り捨てて 181
        // (1 明細ずつ切り捨てると 90 + 90 = 180 になり、1 円ずれる)
        $this->assertSame(2000, $summary->totalInclTax());
        $this->assertSame(181, $summary->totalTax());
        $this->assertSame(1819, $summary->totalExclTax());

        // 明細ごとの内訳は按分され、合計はグループの消費税額と必ず一致する
        $this->assertSame(91, $summary->lineAmounts[0]->taxAmount);
        $this->assertSame(90, $summary->lineAmounts[1]->taxAmount);
        $this->assertSame(181, $summary->lineAmounts[0]->taxAmount + $summary->lineAmounts[1]->taxAmount);
        $this->assertSame(909, $summary->lineAmounts[0]->amountExclTax);
        $this->assertSame(910, $summary->lineAmounts[1]->amountExclTax);
    }

    #[Test]
    public function mixed_tax_rates_are_grouped_before_rounding(): void
    {
        $summary = TaxCalculator::summarize([
            ['amount_incl_tax' => 10800, 'tax_rate_percent' => 10],
            ['amount_incl_tax' => 1080, 'tax_rate_percent' => 8],
        ]);

        // 10%: 10,800 / 1.1 = 9,818.18… → 消費税 981.81… → 981
        //  8%:  1,080 / 1.08 = 1,000     → 消費税 80
        $this->assertSame(11880, $summary->totalInclTax());
        $this->assertSame(1061, $summary->totalTax());
        $this->assertSame(10819, $summary->totalExclTax());

        // 税率ごとの内訳(税率の高い順)
        $this->assertCount(2, $summary->rateAmounts);

        $this->assertSame(10, $summary->rateAmounts[0]->ratePercent);
        $this->assertSame(10800, $summary->rateAmounts[0]->amountInclTax);
        $this->assertSame(981, $summary->rateAmounts[0]->taxAmount);
        $this->assertSame(9819, $summary->rateAmounts[0]->amountExclTax());

        $this->assertSame(8, $summary->rateAmounts[1]->ratePercent);
        $this->assertSame(1080, $summary->rateAmounts[1]->amountInclTax);
        $this->assertSame(80, $summary->rateAmounts[1]->taxAmount);
        $this->assertSame(1000, $summary->rateAmounts[1]->amountExclTax());
    }

    #[Test]
    public function the_allocated_line_taxes_always_add_up_to_the_group_tax(): void
    {
        $lines = [];

        // 端数が出やすい金額を並べる
        foreach ([333, 777, 1234, 9, 50001] as $amount) {
            $lines[] = ['amount_incl_tax' => $amount, 'tax_rate_percent' => 10];
        }

        $summary = TaxCalculator::summarize($lines);

        $allocated = array_sum(array_map(
            static fn ($line): int => $line->taxAmount,
            $summary->lineAmounts,
        ));

        $this->assertSame($summary->totalTax(), $allocated);
        $this->assertSame(
            $summary->totalInclTax(),
            array_sum(array_map(static fn ($line): int => $line->amountInclTax, $summary->lineAmounts)),
        );

        // 税込合計 52,354 / 1.1 = 47,594.54… → 消費税 4,759.45… → 4,759
        $this->assertSame(52354, $summary->totalInclTax());
        $this->assertSame(4759, $summary->totalTax());
    }

    #[Test]
    public function an_empty_deal_has_no_amounts(): void
    {
        $summary = TaxCalculator::summarize([]);

        $this->assertSame(0, $summary->totalInclTax());
        $this->assertSame(0, $summary->totalTax());
        $this->assertSame(0, $summary->totalExclTax());
        $this->assertSame([], $summary->rateAmounts);
        $this->assertSame([], $summary->lineAmounts);
    }
}
