<?php

namespace Database\Seeders;

use App\Enums\ActivityType;
use App\Enums\DealStatus;
use App\Models\Activity;
use App\Models\Deal;
use App\Models\DealItem;
use App\Models\Employee;
use App\Models\Partner;
use App\Models\PartnerContact;
use App\Models\Product;
use App\Models\TaxRate;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * CRM の動作確認用サンプルデータ(本番環境では実行しない)。
 *
 * 共通マスタのサンプル(MasterSampleSeeder)を前提に、
 * 顧客担当者 → 商談 → 明細 → 活動履歴が一通り入った状態を作る。
 *
 * 金額は「税込が正・消費税と税抜は逆算」という設計に沿った整合値を直接入れる。
 * 計算ロジック自体は STEP 4 で実装する。
 */
class CrmSampleSeeder extends Seeder
{
    /** ステータスごとの商談件数 */
    private const DEAL_PLAN = [
        [DealStatus::Prospect, 6],
        [DealStatus::Proposing, 5],
        [DealStatus::Quoted, 4],
        [DealStatus::Won, 7],
        [DealStatus::Lost, 3],
    ];

    public function run(): void
    {
        // 既にサンプルが入っている場合は二重登録しない
        if (Deal::query()->exists()) {
            return;
        }

        $partners = Partner::query()->customers()->active()->orderBy('id')->limit(12)->get();
        $employees = Employee::query()->active()->orderBy('id')->limit(6)->get();
        $products = Product::query()->active()->get();

        /** @var array<int, int> $taxRatePercents 税率マスタの [id => 税率%] */
        $taxRatePercents = TaxRate::query()->pluck('rate_percent', 'id')->all();

        // 共通マスタのサンプルが無い環境(本番相当)では何もしない
        if ($partners->isEmpty() || $employees->isEmpty() || $products->isEmpty()) {
            return;
        }

        $this->createContacts($partners);

        foreach (self::DEAL_PLAN as [$status, $count]) {
            for ($i = 0; $i < $count; $i++) {
                $this->createDeal($status, $partners, $employees, $products, $taxRatePercents);
            }
        }

        $this->createPartnerActivities($partners, $employees);
    }

    /**
     * 会社ごとに 1〜3 名の担当者を作る。
     *
     * @param  Collection<int, Partner>  $partners
     */
    private function createContacts(Collection $partners): void
    {
        foreach ($partners as $partner) {
            PartnerContact::factory()
                ->count(fake()->numberBetween(1, 3))
                ->create(['partner_id' => $partner->id]);
        }
    }

    /**
     * 商談 1 件と、その明細・活動履歴を作る。
     *
     * @param  Collection<int, Partner>  $partners
     * @param  Collection<int, Employee>  $employees
     * @param  Collection<int, Product>  $products
     * @param  array<int, int>  $taxRatePercents
     */
    private function createDeal(DealStatus $status, Collection $partners, Collection $employees, Collection $products, array $taxRatePercents): void
    {
        /** @var Partner $partner */
        $partner = $partners->random();

        $contact = PartnerContact::query()->where('partner_id', $partner->id)->inRandomOrder()->first();

        $orderedAt = $status === DealStatus::Won
            ? fake()->dateTimeBetween('-4 months', '-1 week')->format('Y-m-d')
            : null;

        // 受注済みは「受注日 + 数週間」を納品予定日とし、未到来のものが受注残になるようにする
        $expectedCloseDate = $orderedAt !== null
            ? Carbon::parse($orderedAt)->addDays(fake()->numberBetween(0, 120))->toDateString()
            : fake()->dateTimeBetween('-1 month', '+4 months')->format('Y-m-d');

        $deal = Deal::factory()->create([
            'partner_id' => $partner->id,
            'partner_contact_id' => $contact?->id,
            'employee_id' => $employees->random()->id,
            'status' => $status,
            'probability' => $this->probabilityFor($status),
            'expected_close_date' => $expectedCloseDate,
            'ordered_at' => $orderedAt,
        ]);

        $this->createItems($deal, $products, $taxRatePercents);
        $this->createDealActivities($deal, $employees);
    }

    /**
     * 商談明細を 1〜3 行作り、商談の税込合計を更新する。
     *
     * @param  Collection<int, Product>  $products
     * @param  array<int, int>  $taxRatePercents
     */
    private function createItems(Deal $deal, Collection $products, array $taxRatePercents): void
    {
        // 1 行ごとに合計を計算し直さず、最後にまとめて 1 回だけ計算する
        Deal::withoutAmountRecalculation(function () use ($deal, $products, $taxRatePercents): void {
            foreach ($products->shuffle()->take(fake()->numberBetween(1, 3)) as $product) {
                /** @var Product $product */
                DealItem::factory()->create([
                    'deal_id' => $deal->id,
                    'product_id' => $product->id,
                    'tax_rate_id' => $product->tax_rate_id,
                    'quantity' => fake()->numberBetween(1, 12),
                    // 商品マスタの単価は税込単価(整数)
                    'unit_price' => $product->unit_price,
                    'tax_rate_percent' => $taxRatePercents[(int) $product->tax_rate_id] ?? 10,
                ]);
            }
        });

        // 金額は明細から計算し直す(税率ごとに 1 回だけ切り捨て)
        $deal->recalculateAmounts();
    }

    /**
     * 商談に紐づく活動履歴。
     *
     * @param  Collection<int, Employee>  $employees
     */
    private function createDealActivities(Deal $deal, Collection $employees): void
    {
        Activity::factory()
            ->count(fake()->numberBetween(1, 4))
            ->create([
                'partner_id' => $deal->partner_id,
                'deal_id' => $deal->id,
                'employee_id' => $employees->random()->id,
            ]);
    }

    /**
     * 商談に紐づかない、顧客への活動(定期連絡など)。
     *
     * @param  Collection<int, Partner>  $partners
     * @param  Collection<int, Employee>  $employees
     */
    private function createPartnerActivities(Collection $partners, Collection $employees): void
    {
        foreach ($partners->take(6) as $partner) {
            Activity::factory()->create([
                'partner_id' => $partner->id,
                'deal_id' => null,
                'employee_id' => $employees->random()->id,
                'type' => ActivityType::Phone,
            ]);
        }
    }

    private function probabilityFor(DealStatus $status): int
    {
        return match ($status) {
            DealStatus::Prospect => 10,
            DealStatus::Proposing => 40,
            DealStatus::Quoted => 70,
            DealStatus::Won => 100,
            DealStatus::Lost => 0,
        };
    }
}
