<?php

namespace Database\Factories;

use App\Enums\DealStatus;
use App\Models\Deal;
use App\Models\Employee;
use App\Models\Partner;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Deal>
 */
class DealFactory extends Factory
{
    protected $model = Deal::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'partner_id' => Partner::factory(),
            'employee_id' => Employee::factory(),
            'title' => $this->faker->randomElement([
                '業務システム刷新', '採用管理ツール導入', '保守契約更新',
                'Web サイトリニューアル', '基幹システム連携', 'PC 入替',
            ]).' 一式',
            'status' => DealStatus::Prospect,
            'probability' => $this->faker->randomElement([10, 30, 50, 70, 90]),
            // 金額は明細から積み上げる。既定は 0 のままにしておく(計算は STEP 4)
            'amount_total' => 0,
            'expected_close_date' => $this->faker->dateTimeBetween('now', '+3 months')->format('Y-m-d'),
            'is_active' => true,
        ];
    }

    /**
     * 受注済み(受注日つき)。
     */
    public function won(): self
    {
        return $this->state(fn (): array => [
            'status' => DealStatus::Won,
            'probability' => 100,
            'ordered_at' => $this->faker->dateTimeBetween('-3 months', 'now')->format('Y-m-d'),
        ]);
    }

    /**
     * 失注。
     */
    public function lost(): self
    {
        return $this->state(fn (): array => [
            'status' => DealStatus::Lost,
            'probability' => 0,
        ]);
    }
}
