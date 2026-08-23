<?php

namespace Database\Factories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->randomElement(['ノートPC', 'デスク', 'モニター', 'ライセンス', '保守サービス'])
                .' '.strtoupper($this->faker->bothify('??-###')),
            'unit_price' => $this->faker->numberBetween(1, 500) * 100,
            'unit' => $this->faker->randomElement(['個', '式', '台', '本']),
            'is_active' => true,
        ];
    }
}
