<?php

namespace Database\Factories;

use App\Enums\EntityType;
use App\Enums\PartnerType;
use App\Models\Partner;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Partner>
 */
class PartnerFactory extends Factory
{
    protected $model = Partner::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->company(),
            'partner_type' => $this->faker->randomElement(PartnerType::cases()),
            'entity_type' => $this->faker->randomElement(EntityType::cases()),
            'email' => $this->faker->unique()->companyEmail(),
            'phone' => $this->faker->phoneNumber(),
            'postal_code' => $this->faker->postcode(),
            'address' => $this->faker->address(),
            'is_active' => true,
        ];
    }
}
