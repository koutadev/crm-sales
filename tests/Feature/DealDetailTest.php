<?php

namespace Tests\Feature;

use App\Enums\ActivityType;
use App\Enums\DealStatus;
use App\Enums\PartnerType;
use App\Enums\RoleName;
use App\Models\Activity;
use App\Models\Deal;
use App\Models\DealItem;
use App\Models\Employee;
use App\Models\Partner;
use App\Models\PartnerContact;
use App\Models\Product;
use App\Models\TaxRate;
use App\Models\User;
use App\Support\Crm\TaxCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * 商談詳細の 1 ページ集約(3-G)の検証。
 *
 * 基本情報・金額内訳(税率別を含む)・明細・活動履歴・次アクションが
 * 1 ページで把握でき、活動の追加がその場で完結することを見る。
 * 金額は既存のロジックのまま(表示の整理だけ)。
 */
class DealDetailTest extends TestCase
{
    use RefreshDatabase;

    private Deal $deal;

    private Employee $employee;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-08-15 10:00:00'));

        $customer = Partner::factory()->create([
            'name' => 'アオイ商事株式会社',
            'partner_type' => PartnerType::Customer,
        ]);

        $contact = PartnerContact::factory()->create([
            'partner_id' => $customer->id,
            'name' => '青井 一郎',
        ]);

        $this->employee = Employee::factory()->create(['name' => '営業 太郎']);

        $this->deal = Deal::factory()->create([
            'partner_id' => $customer->id,
            'partner_contact_id' => $contact->id,
            'employee_id' => $this->employee->id,
            'title' => '基幹システム刷新',
            'status' => DealStatus::Quoted,
            'probability' => 60,
            'expected_close_date' => '2026-09-30',
        ]);

        // 税率の違う明細を 2 本(税率別内訳が出ることを確かめるため)
        $standard = TaxRate::query()->where('rate_percent', 10)->first()
            ?? TaxRate::factory()->create(['name' => '標準', 'rate_percent' => 10]);
        $reduced = TaxRate::query()->where('rate_percent', 8)->first()
            ?? TaxRate::factory()->create(['name' => '軽減', 'rate_percent' => 8]);

        $this->item($standard, 110000, 2);
        $this->item($reduced, 10800, 1);

        $this->deal->refresh();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function item(TaxRate $taxRate, int $unitPrice, int $quantity): DealItem
    {
        $product = Product::factory()->create(['unit_price' => $unitPrice, 'tax_rate_id' => $taxRate->id]);
        $amount = $unitPrice * $quantity;
        $tax = TaxCalculator::taxFromInclusive($amount, $taxRate->rate_percent);

        return DealItem::factory()->create([
            'deal_id' => $this->deal->id,
            'product_id' => $product->id,
            'tax_rate_id' => $taxRate->id,
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'tax_rate_percent' => $taxRate->rate_percent,
            'amount_incl_tax' => $amount,
            'tax_amount' => $tax,
            'amount_excl_tax' => $amount - $tax,
        ]);
    }

    private function actingAsRole(RoleName $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role->value);

        $this->actingAs($user);

        return $user;
    }

    #[Test]
    public function the_page_gathers_everything_about_the_deal(): void
    {
        $this->actingAsRole(RoleName::Staff);

        $response = $this->get(route('deals.show', $this->deal->id))->assertOk();

        // 見出し(件名・コード・ステータス)と、その直下の要点
        $response->assertSee('基幹システム刷新')
            ->assertSee($this->deal->code)
            ->assertSee('見積提示')
            ->assertSee('アオイ商事株式会社')
            ->assertSee('営業 太郎')
            ->assertSee('予定クローズ 2026/09/30');

        // 基本情報
        $response->assertSee('基本情報')
            ->assertSee('青井 一郎')
            ->assertSee('60%');

        // 3 つのタブ(概要 / 明細 / 活動)で 1 ページにまとまっている
        $response->assertSee('概要')
            ->assertSee('明細（2）')
            ->assertSee('活動（0）');
    }

    #[Test]
    public function the_amounts_are_shown_with_the_breakdown_by_tax_rate(): void
    {
        $this->actingAsRole(RoleName::Staff);

        // 10%: 220,000（税 20,000） / 8%: 10,800（税 800）
        $summary = $this->deal->amountSummary();
        $this->assertSame(230800, $summary->totalInclTax());
        $this->assertSame(20800, $summary->totalTax());

        $response = $this->get(route('deals.show', $this->deal->id))->assertOk();

        // 上部の金額カード(税込 / 消費税 / 税抜 / 加重見込み)
        $response->assertSee('合計(税込)')
            ->assertSee('うち消費税')
            ->assertSee('税抜')
            ->assertSee('230,800')
            ->assertSee('20,800')
            ->assertSee('210,000')
            // 加重見込み = 230,800 × 60%
            ->assertSee('138,480')
            ->assertSee('確度 60%');

        // 税率別の内訳
        $response->assertSee('金額内訳')
            ->assertSee('税率別')
            ->assertSee('220,000')
            ->assertSee('10,800')
            ->assertSee('20,000');

        // 明細(商品を選んだ時点の単価と税率)
        $response->assertSee('110,000')
            ->assertSee('10%')
            ->assertSee('8%');
    }

    #[Test]
    public function the_next_action_is_the_closest_future_activity(): void
    {
        $user = $this->actingAsRole(RoleName::Staff);

        // 済んだ活動と、これからの予定を 2 件
        Activity::factory()->create([
            'deal_id' => $this->deal->id,
            'partner_id' => $this->deal->partner_id,
            'employee_id' => $this->employee->id,
            'type' => ActivityType::Visit,
            'activity_at' => '2026-08-10 10:00',
            'note' => '訪問して要件を確認した',
        ]);
        Activity::factory()->create([
            'deal_id' => $this->deal->id,
            'partner_id' => $this->deal->partner_id,
            'employee_id' => $this->employee->id,
            'type' => ActivityType::Phone,
            'activity_at' => '2026-08-20 14:00',
            'note' => '見積の感触を確認する',
        ]);
        Activity::factory()->create([
            'deal_id' => $this->deal->id,
            'partner_id' => $this->deal->partner_id,
            'employee_id' => $this->employee->id,
            'type' => ActivityType::Visit,
            'activity_at' => '2026-08-18 09:00',
            'note' => '打ち合わせ',
        ]);

        $html = $this->get(route('deals.show', $this->deal->id))->assertOk()->getContent();

        // いちばん近い予定が「次アクション」に出る
        $next = substr($html, strpos($html, '次アクション') ?: 0, 1200);
        $this->assertStringContainsString('2026/08/18 09:00', $next);
        $this->assertStringContainsString('打ち合わせ', $next);
        $this->assertStringNotContainsString('見積の感触を確認する', $next);

        // 予定は活動履歴でも「予定」と分かる
        $this->assertStringContainsString('予定', $html);
        $this->assertStringContainsString('活動（3）', $html);
    }

    #[Test]
    public function it_says_when_there_is_no_next_action(): void
    {
        $this->actingAsRole(RoleName::Staff);

        Activity::factory()->create([
            'deal_id' => $this->deal->id,
            'partner_id' => $this->deal->partner_id,
            'employee_id' => $this->employee->id,
            'activity_at' => '2026-08-10 10:00',
        ]);

        $this->get(route('deals.show', $this->deal->id))
            ->assertOk()
            ->assertSee('予定されている活動はありません。');
    }

    #[Test]
    public function an_activity_can_be_added_from_the_page_and_the_tab_reopens(): void
    {
        $this->actingAsRole(RoleName::Staff);

        // 追加フォームは詳細ページの中に用意されている(モーダル)
        $this->get(route('deals.show', $this->deal->id))
            ->assertOk()
            ->assertSee('活動を追加')
            ->assertSee('open-modal', false);

        $this->post(route('deals.activities.store', $this->deal->id), [
            'employee_id' => $this->employee->id,
            'type' => ActivityType::Phone->value,
            'activity_at' => '2026-08-16 09:30',
            'note' => '電話で日程を調整した。',
        ])->assertRedirect(route('deals.show', ['id' => $this->deal->id, 'tab' => 'activities']));

        $this->get(route('deals.show', ['id' => $this->deal->id, 'tab' => 'activities']))
            ->assertOk()
            ->assertSee('電話で日程を調整した。')
            // ?tab= は開くタブとしてそのまま使われる
            ->assertSee("tab: 'activities'", false);
    }

    #[Test]
    public function a_failed_activity_input_reopens_the_form(): void
    {
        $this->actingAsRole(RoleName::Staff);

        // エラー時は活動タブが開き、モーダルも開いた状態で戻る
        $html = $this->from(route('deals.show', $this->deal->id))
            ->followingRedirects()
            ->post(route('deals.activities.store', $this->deal->id), [
                'employee_id' => $this->employee->id,
                'type' => ActivityType::Phone->value,
                'activity_at' => '',
                'note' => '',
            ])
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString("tab: 'activities'", $html);
        $this->assertStringContainsString('deal-activity', $html);
    }

    #[Test]
    public function a_viewer_sees_the_page_without_the_editing_actions(): void
    {
        $this->actingAsRole(RoleName::Viewer);

        $this->get(route('deals.show', $this->deal->id))
            ->assertOk()
            ->assertSee('基幹システム刷新')
            ->assertSee('230,800')
            ->assertDontSee('活動を追加')
            ->assertDontSee('明細を編集');
    }

    #[Test]
    public function the_page_does_not_run_more_queries_as_the_deal_grows(): void
    {
        $this->actingAsRole(RoleName::Staff);

        $standard = TaxRate::query()->where('rate_percent', 10)->firstOrFail();

        // 活動が 0 件だと実施者の読み込みが起きないので、1 件だけ入れた状態を基準にする
        Activity::factory()->create([
            'deal_id' => $this->deal->id,
            'partner_id' => $this->deal->partner_id,
            'employee_id' => $this->employee->id,
            'activity_at' => '2026-08-12 10:00',
        ]);

        $this->get(route('deals.show', $this->deal->id))->assertOk();   // 権限などを温める

        $baseline = $this->countQueries(fn () => $this->get(route('deals.show', $this->deal->id))->assertOk());

        // 明細と活動を増やす(次アクションは取得済みの活動から選ぶだけ)
        for ($i = 0; $i < 5; $i++) {
            $this->item($standard, 5500, 1);
        }

        Activity::factory()->count(5)->create([
            'deal_id' => $this->deal->id,
            'partner_id' => $this->deal->partner_id,
            'employee_id' => $this->employee->id,
            'activity_at' => '2026-08-14 10:00',
        ]);

        $scaled = $this->countQueries(fn () => $this->get(route('deals.show', $this->deal->id))->assertOk());

        $this->assertSame($baseline, $scaled, '明細や活動が増えてもクエリ本数は変わらない。');
        $this->assertLessThanOrEqual(11, $scaled);
    }

    /**
     * 1 リクエストで走ったクエリの本数。
     */
    private function countQueries(callable $callback): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();

        $callback();

        $count = count(DB::getQueryLog());

        DB::disableQueryLog();

        return $count;
    }
}
