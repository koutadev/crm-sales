<?php

namespace Database\Factories;

use App\Enums\EmploymentStatus;
use App\Models\Employee;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Employee>
 */
class EmployeeFactory extends Factory
{
    protected $model = Employee::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->name(),
            'email' => $this->faker->unique()->safeEmail(),
            'employment_status' => EmploymentStatus::Active,
            'is_active' => true,
        ];
    }

    public function retired(): self
    {
        return $this->state(fn (): array => [
            'employment_status' => EmploymentStatus::Retired,
            'is_active' => false,
        ]);
    }
}
