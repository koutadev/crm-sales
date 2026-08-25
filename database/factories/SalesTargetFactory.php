<?php

namespace Database\Factories;

use App\Enums\TargetScope;
use App\Models\SalesTarget;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SalesTarget>
 */
class SalesTargetFactory extends Factory
{
    protected $model = SalesTarget::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'scope' => TargetScope::Company,
            'target_id' => null,
            'year' => (int) now()->year,
            'month' => (int) now()->month,
            'amount' => fake()->numberBetween(5, 50) * 1000000,
            'is_active' => true,
        ];
    }
}
