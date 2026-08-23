<?php

namespace Database\Factories;

use App\Models\TaxRate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TaxRate>
 */
class TaxRateFactory extends Factory
{
    protected $model = TaxRate::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => '標準',
            'rate_percent' => 10,
            'effective_from' => '2019-10-01',
            'is_active' => true,
        ];
    }

    /**
     * 軽減税率(8%)。
     */
    public function reduced(): self
    {
        return $this->state(fn (): array => [
            'name' => '軽減',
            'rate_percent' => 8,
        ]);
    }
}
