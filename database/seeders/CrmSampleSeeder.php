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
 * CRM のデモデータ(本番環境では実行しない)。
 *
 * 仮想クライアント「アルティス・ソリューションズ」の 1 年ぶんの営業活動を再現する。
 *   - 商談は約 320 件。ステータスが偏らないよう配分し、受注は直近 12 か月に散らす
 *   - 明細は商品カタログから 1〜4 行。3 割ほどの商談に軽減税率(8%)の商品を混ぜる
 *   - 金額は必ず Deal::recalculateAmounts() を通す(手置きで不整合を作らない)
 *
 * 大量投入のため操作ログは止めてある。デモでは利用者自身の操作だけがログに残る。
 */
class CrmSampleSeeder extends Seeder
{
    /** ステータスごとの商談件数 */
    private const DEAL_PLAN = [
        [DealStatus::Prospect, 70],
        [DealStatus::Proposing, 60],
        [DealStatus::Quoted, 45],
        [DealStatus::Won, 110],
        [DealStatus::Lost, 35],
    ];

    private const TITLES = [
        '基幹システム刷新', '採用管理システム導入', 'コーポレートサイトリニューアル',
        'EC サイト構築', '営業支援ツール導入', '展示会出展サポート',
        'リスティング広告 運用支援', 'エンジニア採用支援', '保守契約更新',
        'サーバ移行支援', '社内研修プログラム', 'スマートフォンアプリ開発',
        'データ分析基盤構築', 'ノベルティ制作', 'RPA 導入支援',
        'セキュリティ診断', '会員サイト改修', '受発注システム開発',
        '採用ブランディング支援', '業務フロー可視化コンサル',
    ];

    /** 次アクション(これからの予定)の文面。 */
    private const NEXT_ACTIONS = [
        '次回打ち合わせ。要件の詰めと概算スケジュールの共有。',
        '見積の内容についてオンラインで説明。',
        '先方の稟議状況を確認する電話。',
        '追加要望のヒアリング（訪問）。',
        '契約書ドラフトの確認依頼。',
    ];

    private const NOTES = [
        '初回訪問。現行システムの運用負荷が高く、刷新を検討中とのこと。',
        '提案書を送付。来週あらためて打ち合わせ予定。',
        '見積の内容について質問あり。保守範囲を補足説明した。',
        '先方の予算取りが年度内とのことで、スケジュールを前倒しで調整。',
        '稟議が通ったとの連絡。契約書の準備に入る。',
        '担当者が異動のため、後任と顔合わせ。',
        '競合他社も比較検討中とのこと。価格よりも運用体制を重視している。',
        '別部署からも同様の相談があるとの情報を得た。横展開を狙う。',
        'キックオフ日程を調整。開発チームに引き継ぎ予定。',
        '見送りの連絡。次年度に再検討したいとのこと。',
    ];

    public function run(): void
    {
        // 既にサンプルが入っている場合は二重登録しない
        if (Deal::query()->exists()) {
            return;
        }

        $customers = Partner::query()->customers()->active()->orderBy('id')->get();
        $salesReps = Employee::query()->active()->orderBy('id')->limit(8)->get();
        $products = Product::query()->active()->orderBy('id')->get();

        // 共通マスタのサンプルが無い環境(本番相当)では何もしない
        if ($customers->isEmpty() || $salesReps->isEmpty() || $products->isEmpty()) {
            return;
        }

        config(['activity_log.enabled' => false]);

        try {
            $contacts = $this->createContacts($customers);

            /** @var array<int, int> $taxRatePercents */
            $taxRatePercents = TaxRate::query()->pluck('rate_percent', 'id')->all();

            [$standardProducts, $reducedProducts] = $products->partition(
                fn (Product $product): bool => ($taxRatePercents[(int) $product->tax_rate_id] ?? 10) === 10
            );

            $repPool = $this->weightedSalesReps($salesReps);

            foreach (self::DEAL_PLAN as [$status, $count]) {
                for ($index = 0; $index < $count; $index++) {
                    $this->createDeal(
                        status: $status,
                        customer: fake()->randomElement($customers->all()),
                        contacts: $contacts,
                        employee: fake()->randomElement($repPool->all()),
                        standardProducts: $standardProducts,
                        reducedProducts: $reducedProducts,
                        taxRatePercents: $taxRatePercents,
                        orderedThisMonth: $status === DealStatus::Won && $index < 8,
                    );
                }
            }

            $this->createCustomerActivities($customers, $salesReps);
        } finally {
            config(['activity_log.enabled' => true]);
        }
    }

    /**
     * 会社ごとに 1〜3 名の担当者を作り、会社 ID で引けるようにして返す。
     *
     * @param  Collection<int, Partner>  $customers
     * @return Collection<int, Collection<int, PartnerContact>>
     */
    private function createContacts(Collection $customers): Collection
    {
        $contacts = collect();

        foreach ($customers as $customer) {
            $contacts->put(
                $customer->id,
                PartnerContact::factory()
                    ->count(fake()->numberBetween(1, 3))
                    ->create(['partner_id' => $customer->id]),
            );
        }

        return $contacts;
    }

    /**
     * 担当者別売上のランキングに差が出るよう、担当の当たりやすさに重みを付ける。
     *
     * @param  Collection<int, Employee>  $salesReps
     * @return Collection<int, Employee>
     */
    private function weightedSalesReps(Collection $salesReps): Collection
    {
        $pool = collect();

        foreach ($salesReps->values() as $index => $employee) {
            foreach (range(0, max(1, 8 - $index)) as $ignored) {
                $pool->push($employee);
            }
        }

        return $pool;
    }

    /**
     * 商談 1 件と、その明細・活動履歴を作る。
     *
     * @param  Collection<int, Collection<int, PartnerContact>>  $contacts
     * @param  Collection<int, Product>  $standardProducts
     * @param  Collection<int, Product>  $reducedProducts
     * @param  array<int, int>  $taxRatePercents
     */
    private function createDeal(
        DealStatus $status,
        Partner $customer,
        Collection $contacts,
        Employee $employee,
        Collection $standardProducts,
        Collection $reducedProducts,
        array $taxRatePercents,
        bool $orderedThisMonth,
    ): void {
        [$orderedAt, $expectedCloseDate] = $this->datesFor($status, $orderedThisMonth);

        $customerContacts = $contacts->get($customer->id);

        $deal = Deal::factory()->create([
            'partner_id' => $customer->id,
            // 1 割ほどは先方担当が未設定(実運用でもよくある)
            'partner_contact_id' => $this->contactIdFor($customerContacts),
            'employee_id' => $employee->id,
            'title' => fake()->randomElement(self::TITLES),
            'status' => $status,
            'probability' => $this->probabilityFor($status),
            'expected_close_date' => $expectedCloseDate,
            'ordered_at' => $orderedAt,
        ]);

        $this->createItems($deal, $standardProducts, $reducedProducts, $taxRatePercents);
        $this->createDealActivities($deal, $employee, $expectedCloseDate);
    }

    /**
     * ステータスに応じた受注日・予定クローズ日。
     *
     * @return array{0: string|null, 1: string}
     */
    private function datesFor(DealStatus $status, bool $orderedThisMonth): array
    {
        if ($status === DealStatus::Won) {
            $orderedAt = $orderedThisMonth
                ? fake()->dateTimeBetween(Carbon::now()->startOfMonth(), Carbon::now())
                : fake()->dateTimeBetween('-12 months', '-1 month');

            // 納品予定は受注日の後。まだ到来していないものが受注残になる
            return [
                $orderedAt->format('Y-m-d'),
                Carbon::instance($orderedAt)->addDays(fake()->numberBetween(0, 150))->toDateString(),
            ];
        }

        if ($status === DealStatus::Lost) {
            return [null, fake()->dateTimeBetween('-8 months', '-1 week')->format('Y-m-d')];
        }

        // 進行中。少しだけ予定日を過ぎたものも混ぜる
        return [null, fake()->dateTimeBetween('-3 weeks', '+5 months')->format('Y-m-d')];
    }

    /**
     * 明細を 1〜4 行作り、金額を計算し直す。
     *
     * @param  Collection<int, Product>  $standardProducts
     * @param  Collection<int, Product>  $reducedProducts
     * @param  array<int, int>  $taxRatePercents
     */
    private function createItems(
        Deal $deal,
        Collection $standardProducts,
        Collection $reducedProducts,
        array $taxRatePercents,
    ): void {
        $picked = collect(fake()->randomElements(
            $standardProducts->all(),
            min(fake()->numberBetween(1, 3), $standardProducts->count()),
        ));

        // 3 割ほどの商談には軽減税率(8%)の商品を混ぜ、税率別の内訳が出るようにする
        if ($reducedProducts->isNotEmpty() && fake()->boolean(30)) {
            $picked = $picked->push(fake()->randomElement($reducedProducts->all()));
        }

        // 1 行ごとに合計を計算し直さず、最後にまとめて 1 回だけ計算する
        Deal::withoutAmountRecalculation(function () use ($deal, $picked, $taxRatePercents): void {
            foreach ($picked as $product) {
                /** @var Product $product */
                DealItem::factory()->create([
                    'deal_id' => $deal->id,
                    'product_id' => $product->id,
                    'tax_rate_id' => $product->tax_rate_id,
                    'quantity' => $this->quantityFor($product),
                    // 商品マスタの単価は税込単価(整数)。案件ごとの値引きも少し混ぜる
                    'unit_price' => $this->unitPriceFor($product),
                    'tax_rate_percent' => $taxRatePercents[(int) $product->tax_rate_id] ?? 10,
                ]);
            }
        });

        // 金額は明細から計算し直す(税率ごとに 1 回だけ切り捨て)
        $deal->recalculateAmounts();
    }

    /**
     * 単価の高い商品は少なめ、安い商品は多めの数量にする。
     */
    private function quantityFor(Product $product): int
    {
        return match (true) {
            $product->unit_price >= 1000000 => fake()->numberBetween(1, 2),
            $product->unit_price >= 100000 => fake()->numberBetween(1, 6),
            $product->unit_price >= 10000 => fake()->numberBetween(1, 12),
            default => fake()->numberBetween(10, 120),
        };
    }

    /**
     * 2 割ほどの明細は案件ごとの値引き価格にする(端数が出るようにする)。
     */
    private function unitPriceFor(Product $product): int
    {
        if (! fake()->boolean(20)) {
            return $product->unit_price;
        }

        return (int) round($product->unit_price * fake()->numberBetween(85, 97) / 100);
    }

    /**
     * 商談に紐づく活動履歴。予定クローズ日の手前に数件並べる。
     */
    private function createDealActivities(Deal $deal, Employee $employee, string $expectedCloseDate): void
    {
        if (! fake()->boolean(75)) {
            return;
        }

        $until = Carbon::parse($expectedCloseDate)->min(Carbon::now());

        Activity::factory()
            ->count(fake()->numberBetween(1, 4))
            ->create([
                'partner_id' => $deal->partner_id,
                'deal_id' => $deal->id,
                'employee_id' => $employee->id,
                'activity_at' => fn (): string => fake()
                    ->dateTimeBetween($until->copy()->subDays(90), $until)
                    ->format('Y-m-d H:i:s'),
                'note' => fn (): string => fake()->randomElement(self::NOTES),
            ]);

        // 進行中の商談には「次アクション」として先の予定も入れておく
        // (商談詳細の上部に次にやることが出る)
        if ($deal->status->isOpen() && fake()->boolean(60)) {
            Activity::factory()->create([
                'partner_id' => $deal->partner_id,
                'deal_id' => $deal->id,
                'employee_id' => $employee->id,
                'activity_at' => fake()
                    ->dateTimeBetween(Carbon::now()->addDay(), Carbon::now()->addDays(14))
                    ->format('Y-m-d H:i:s'),
                'note' => fake()->randomElement(self::NEXT_ACTIONS),
            ]);
        }
    }

    /**
     * 商談に紐づかない、顧客への活動(定期連絡など)。
     *
     * @param  Collection<int, Partner>  $customers
     * @param  Collection<int, Employee>  $salesReps
     */
    private function createCustomerActivities(Collection $customers, Collection $salesReps): void
    {
        foreach (fake()->randomElements($customers->all(), min(20, $customers->count())) as $customer) {
            /** @var Partner $customer */
            Activity::factory()->create([
                'partner_id' => $customer->id,
                'deal_id' => null,
                'employee_id' => fake()->randomElement($salesReps->all())->id,
                'type' => ActivityType::Phone,
                'note' => '定期のご挨拶。年度末に向けた予算感をヒアリング。',
            ]);
        }
    }

    /**
     * 先方担当。1 割ほどは未設定にする(実運用でもよくある)。
     *
     * @param  Collection<int, PartnerContact>|null  $contacts
     */
    private function contactIdFor(?Collection $contacts): ?int
    {
        if ($contacts === null || $contacts->isEmpty() || ! fake()->boolean(90)) {
            return null;
        }

        return fake()->randomElement($contacts->all())->id;
    }

    private function probabilityFor(DealStatus $status): int
    {
        return match ($status) {
            DealStatus::Prospect => fake()->randomElement([10, 20]),
            DealStatus::Proposing => fake()->randomElement([30, 40, 50]),
            DealStatus::Quoted => fake()->randomElement([60, 70, 80]),
            DealStatus::Won => 100,
            DealStatus::Lost => 0,
        };
    }
}
