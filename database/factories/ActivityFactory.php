<?php

namespace Database\Factories;

use App\Enums\ActivityType;
use App\Models\Activity;
use App\Models\Employee;
use App\Models\Partner;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Activity>
 */
class ActivityFactory extends Factory
{
    protected $model = Activity::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'partner_id' => Partner::factory(),
            'employee_id' => Employee::factory(),
            'type' => $this->faker->randomElement(ActivityType::cases()),
            'activity_at' => $this->faker->dateTimeBetween('-2 months', 'now'),
            'note' => $this->faker->randomElement([
                '先方の課題をヒアリング。現行システムの運用負荷が高いとのこと。',
                '提案書を送付。来週あらためて打ち合わせ予定。',
                '見積の内容について質問あり。保守範囲を補足説明した。',
                '稟議が通ったとの連絡。契約書の準備に入る。',
                '担当者が異動のため、後任と顔合わせ。',
            ]),
            'is_active' => true,
        ];
    }
}
