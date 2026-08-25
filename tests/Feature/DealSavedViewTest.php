<?php

namespace Tests\Feature;

use App\Enums\DealStatus;
use App\Enums\PartnerType;
use App\Enums\RoleName;
use App\Models\Deal;
use App\Models\Employee;
use App\Models\Partner;
use App\Models\SavedView;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * 商談一覧の状態保持と保存ビュー(3-D)の検証。
 *
 * 期間・基準日・顧客・営業担当・ステータス・キーワード・並び順という
 * 今回までに増えた条件が、まとめて記憶され、ビューとして呼び出せることを見る。
 */
class DealSavedViewTest extends TestCase
{
    use RefreshDatabase;

    private Partner $customer;

    private Partner $otherCustomer;

    private Employee $employee;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-08-15 10:00:00'));

        $this->customer = Partner::factory()->create([
            'name' => 'アオイ商事株式会社',
            'partner_type' => PartnerType::Customer,
        ]);
        $this->otherCustomer = Partner::factory()->create([
            'name' => 'イロハ物産',
            'partner_type' => PartnerType::Customer,
        ]);
        $this->employee = Employee::factory()->create(['name' => '営業 太郎']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function actingAsStaff(): User
    {
        $user = User::factory()->create();
        $user->assignRole(RoleName::Staff->value);

        $this->actingAs($user);

        return $user;
    }

    private function seedDeals(): void
    {
        Deal::factory()->create([
            'partner_id' => $this->customer->id,
            'employee_id' => $this->employee->id,
            'title' => '今月受注の案件',
            'status' => DealStatus::Won,
            'probability' => 100,
            'amount_total' => 330000,
            'expected_close_date' => '2026-08-20',
            'ordered_at' => '2026-08-10',
        ]);
        Deal::factory()->create([
            'partner_id' => $this->otherCustomer->id,
            'employee_id' => $this->employee->id,
            'title' => '来月クローズの案件',
            'status' => DealStatus::Proposing,
            'probability' => 60,
            'amount_total' => 220000,
            'expected_close_date' => '2026-09-05',
            'ordered_at' => null,
        ]);
    }

    /**
     * 今回までに増えた条件をひととおり指定した URL。
     *
     * @return array<string, string|int>
     */
    private function allConditions(): array
    {
        return [
            'q' => '案件',
            'status' => DealStatus::Won->value,
            'partner_id' => $this->customer->id,
            'employee_id' => $this->employee->id,
            'period_basis' => 'ordered_at',
            'period_preset' => 'this_month',
            'probability_min' => '50',
            'sort' => 'amount_total',
            'direction' => 'asc',
        ];
    }

    #[Test]
    public function every_condition_including_the_period_and_sort_is_remembered(): void
    {
        $this->actingAsStaff();
        $this->seedDeals();

        $this->get(route('deals.index', $this->allConditions()))
            ->assertOk()
            ->assertSee('今月受注の案件')
            ->assertDontSee('来月クローズの案件');

        // 別の画面を挟んでも、条件がまとめて残っている
        $this->get(route('customers.index', ['reset' => 1]))->assertOk();

        $html = $this->get(route('deals.index'))->assertOk()->getContent();

        $this->assertStringContainsString('今月受注の案件', $html);
        $this->assertStringNotContainsString('来月クローズの案件', $html);
        // 並び順・キーワード・期間も復元される
        $this->assertStringContainsString('direction=desc', $html, '昇順で表示中なので、次に押すと降順になる。');
        $this->assertStringContainsString('value="案件"', $html);
        $this->assertStringContainsString('ordered_at', $html);
    }

    #[Test]
    public function the_list_can_be_filtered_by_probability(): void
    {
        $this->actingAsStaff();
        $this->seedDeals();

        Deal::factory()->create([
            'partner_id' => $this->customer->id,
            'employee_id' => $this->employee->id,
            'title' => '確度の低い案件',
            'status' => DealStatus::Prospect,
            'probability' => 10,
            'amount_total' => 110000,
            'expected_close_date' => '2026-08-25',
        ]);

        $this->get(route('deals.index', ['probability_min' => '50']))
            ->assertOk()
            ->assertDontSee('確度の低い案件');

        // 他の条件と同じように記憶される
        $this->get(route('deals.index'))
            ->assertOk()
            ->assertDontSee('確度の低い案件');
    }

    #[Test]
    public function the_conditions_can_be_saved_as_a_view_and_called_back(): void
    {
        $user = $this->actingAsStaff();
        $this->seedDeals();

        // 画面と同じように、いまの条件を添えて保存する
        $this->post(route('saved-views.store'), [
            'table_key' => 'deals',
            'name' => '今月の受注（アオイ商事）',
            'redirect_to' => '/deals',
            'conditions' => $this->allConditions(),
        ])->assertRedirect();

        $view = SavedView::query()->firstOrFail();
        $this->assertSame($user->id, $view->user_id);
        $this->assertSame('deals', $view->table_key);
        $this->assertSame('ordered_at', $view->conditions['period_basis']);
        $this->assertSame('50', $view->conditions['probability_min']);

        // いったん全件に戻してから、ビューで呼び戻す
        $this->get(route('deals.index', ['reset' => 1]))
            ->assertOk()
            ->assertSee('来月クローズの案件');

        $this->get(route('deals.index', ['view' => $view->id]))
            ->assertOk()
            ->assertSee('今月受注の案件')
            ->assertDontSee('来月クローズの案件')
            ->assertSee('今月の受注（アオイ商事）');
    }

    #[Test]
    public function the_summary_and_csv_follow_the_called_view(): void
    {
        $user = $this->actingAsStaff();
        $this->seedDeals();

        $view = SavedView::factory()->for($user)->create([
            'table_key' => 'deals',
            'name' => '受注のみ',
            'conditions' => ['status' => DealStatus::Won->value],
        ]);

        // 上部サマリはビュー適用後の集計になる
        $this->get(route('deals.index', ['view' => $view->id]))
            ->assertOk()
            ->assertSee('330,000')
            ->assertDontSee('550,000');

        // CSV も同じ条件で出る
        $csv = $this->get(route('deals.export', ['view' => $view->id]))->assertOk()->streamedContent();

        $this->assertStringContainsString('今月受注の案件', $csv);
        $this->assertStringNotContainsString('来月クローズの案件', $csv);
    }

    #[Test]
    public function a_view_can_be_deleted_from_the_list(): void
    {
        $user = $this->actingAsStaff();

        $view = SavedView::factory()->for($user)->create([
            'table_key' => 'deals',
            'name' => '消えるビュー',
        ]);

        $this->get(route('deals.index', ['reset' => 1]))->assertOk()->assertSee('消えるビュー');

        $this->delete(route('saved-views.destroy', $view->id), ['redirect_to' => '/deals'])
            ->assertRedirect();

        $this->assertSame(0, SavedView::query()->count());

        // プルダウンから消える(直後の画面には「削除しました」のトーストが出るので、リンクの有無で見る)
        $this->get(route('deals.index'))
            ->assertOk()
            ->assertDontSee(route('deals.index', ['view' => $view->id]));
    }

    #[Test]
    public function views_of_other_users_are_not_visible_on_the_deal_list(): void
    {
        $other = User::factory()->create();
        $view = SavedView::factory()->for($other)->create([
            'table_key' => 'deals',
            'name' => '他人のビュー',
            'conditions' => ['status' => DealStatus::Won->value],
        ]);

        $this->actingAsStaff();
        $this->seedDeals();

        $this->get(route('deals.index', ['reset' => 1]))
            ->assertOk()
            ->assertDontSee('他人のビュー');

        // ID を直接指定しても条件は適用されない
        $this->get(route('deals.index', ['view' => $view->id]))
            ->assertOk()
            ->assertSee('来月クローズの案件');
    }

    #[Test]
    public function a_view_of_another_list_is_not_applied(): void
    {
        $user = $this->actingAsStaff();
        $this->seedDeals();

        // 顧客一覧のビュー ID を商談一覧に渡しても無視される
        $view = SavedView::factory()->for($user)->create([
            'table_key' => 'customers',
            'name' => '顧客のビュー',
            'conditions' => ['status' => DealStatus::Won->value],
        ]);

        $this->get(route('deals.index', ['view' => $view->id]))
            ->assertOk()
            ->assertSee('来月クローズの案件');
    }

    #[Test]
    public function the_default_view_opens_automatically(): void
    {
        $user = $this->actingAsStaff();
        $this->seedDeals();

        SavedView::factory()->for($user)->create([
            'table_key' => 'deals',
            'name' => '進行中だけ',
            'conditions' => ['status' => 'open'],
            'is_default' => true,
        ]);

        $this->get(route('deals.index'))
            ->assertOk()
            ->assertSee('来月クローズの案件')
            ->assertDontSee('今月受注の案件');
    }
}
