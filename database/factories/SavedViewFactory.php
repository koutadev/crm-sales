<?php

namespace Database\Factories;

use App\Models\SavedView;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SavedView>
 */
class SavedViewFactory extends Factory
{
    protected $model = SavedView::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'table_key' => 'employees',
            'name' => '保存ビュー '.fake()->unique()->numberBetween(1, 9999),
            'conditions' => [],
            'is_default' => false,
        ];
    }
}
