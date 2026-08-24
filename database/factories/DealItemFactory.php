<?php

namespace Database\Factories;

use App\Models\Deal;
use App\Models\DealItem;
use App\Models\Product;
use App\Models\TaxRate;
use App\Support\Crm\TaxCalculator;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DealItem>
 *
 * 金額は「税込が正」。消費税・税抜は税込からの逆算値。
 * 複数明細のときは Deal::recalculateAmounts() が税率ごとにまとめて計算し直す。
 */
class DealItemFactory extends Factory
{
    protected $model = DealItem::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $quantity = $this->faker->numberBetween(1, 10);
        $unitPrice = $this->faker->numberBetween(10, 500) * 1000;
        $ratePercent = 10;

        $amountInclTax = $unitPrice * $quantity;
        $taxAmount = TaxCalculator::taxFromInclusive($amountInclTax, $ratePercent);

        return [
            'deal_id' => Deal::factory(),
            'product_id' => Product::factory(),
            'tax_rate_id' => TaxRate::factory(),
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'tax_rate_percent' => $ratePercent,
            'amount_incl_tax' => $amountInclTax,
            'tax_amount' => $taxAmount,
            'amount_excl_tax' => $amountInclTax - $taxAmount,
            'is_active' => true,
        ];
    }
}
