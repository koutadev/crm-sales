<?php

namespace Database\Factories;

use App\Models\Partner;
use App\Models\PartnerContact;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PartnerContact>
 */
class PartnerContactFactory extends Factory
{
    protected $model = PartnerContact::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'partner_id' => Partner::factory(),
            'name' => $this->faker->name(),
            'department' => $this->faker->randomElement(['営業部', '情報システム部', '購買部', '経営企画室', '総務部']),
            'position' => $this->faker->randomElement(['部長', '課長', '係長', '主任', '担当']),
            'email' => $this->faker->unique()->safeEmail(),
            'phone' => $this->faker->numerify('03-####-####'),
            'is_active' => true,
        ];
    }
}
