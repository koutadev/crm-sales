<?php

namespace Database\Factories;

use App\Enums\OrganizationType;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Organization>
 */
class OrganizationFactory extends Factory
{
    protected $model = Organization::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->city().'店',
            'type' => OrganizationType::Store,
            'parent_id' => null,
            'is_active' => true,
        ];
    }

    public function region(): self
    {
        return $this->state(fn (): array => [
            'name' => fake()->city().'地域',
            'type' => OrganizationType::Region,
            'parent_id' => null,
        ]);
    }

    public function area(?Organization $parent = null): self
    {
        return $this->state(fn (): array => [
            'name' => fake()->city().'エリア',
            'type' => OrganizationType::Area,
            'parent_id' => $parent !== null ? $parent->id : Organization::factory()->region(),
        ]);
    }

    /** Factory::store() と衝突するため、店舗の指定はこの名前にしてある。 */
    public function asStore(?Organization $parent = null): self
    {
        return $this->state(fn (): array => [
            'name' => fake()->city().'店',
            'type' => OrganizationType::Store,
            'parent_id' => $parent !== null ? $parent->id : Organization::factory()->area(),
        ]);
    }
}
