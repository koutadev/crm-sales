<?php

namespace Database\Factories;

use App\Models\Deal;
use App\Models\DealItem;
use App\Models\Product;
use App\Models\TaxRate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DealItem>
 *
 * 金額は「税込が正」。消費税・税抜は税込からの逆算値を入れておく
 * (計算ロジック本体は STEP 4 で実装する)。
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

        return array_merge(
            [
                'deal_id' => Deal::factory(),
                'product_id' => Product::factory(),
                'tax_rate_id' => TaxRate::factory(),
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'tax_rate_percent' => $ratePercent,
                'is_active' => true,
            ],
            self::amounts($unitPrice * $quantity, $ratePercent),
        );
    }

    /**
     * 税込金額から消費税額・税抜金額を逆算する(明細単位・切り捨て)。
     *
     * @return array{amount_incl_tax: int, tax_amount: int, amount_excl_tax: int}
     */
    public static function amounts(int $amountInclTax, int $ratePercent): array
    {
        $taxAmount = (int) floor($amountInclTax - $amountInclTax / (1 + $ratePercent / 100));

        return [
            'amount_incl_tax' => $amountInclTax,
            'tax_amount' => $taxAmount,
            'amount_excl_tax' => $amountInclTax - $taxAmount,
        ];
    }
}
