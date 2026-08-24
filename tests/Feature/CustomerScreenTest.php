<?php

namespace Tests\Feature;

use App\Enums\PartnerType;
use App\Enums\RoleName;
use App\Models\Activity;
use App\Models\Deal;
use App\Models\Partner;
use App\Models\PartnerContact;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * 顧客(会社)管理画面の検証。
 *
 * 一覧の金額集計・詳細タブ・担当者のインライン管理・権限を見る。
 */
class CustomerScreenTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsRole(RoleName $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role->value);

        $this->actingAs($user);

        return $user;
    }

    private function customer(string $name = 'テスト商事株式会社'): Partner
    {
        return Partner::factory()->create([
            'name' => $name,
            'partner_type' => PartnerType::Customer,
        ]);
    }

    #[Test]
    public function the_customer_list_shows_the_aggregated_amounts(): void
    {
        $this->actingAsRole(RoleName::Staff);

        $customer = $this->customer();

        Deal::factory()->won()->create(['partner_id' => $customer->id, 'amount_total' => 330000]);
        Deal::factory()->won()->create(['partner_id' => $customer->id, 'amount_total' => 110000]);
        Deal::factory()->create(['partner_id' => $customer->id, 'amount_total' => 220000]);
        Deal::factory()->lost()->create(['partner_id' => $customer->id, 'amount_total' => 999999]);

        $this->get(route('customers.index'))
            ->assertOk()
            ->assertSee('テスト商事株式会社')
            ->assertSee('440,000')   // 累計売上 = 受注のみ
            ->assertSee('220,000')   // 進行中 = 受注 / 失注以外
            ->assertDontSee('999,999');
    }

    #[Test]
    public function suppliers_are_not_listed_as_customers(): void
    {
        $this->actingAsRole(RoleName::Staff);

        $this->customer('得意先テスト');
        Partner::factory()->create(['name' => '仕入先テスト', 'partner_type' => PartnerType::Supplier]);

        $this->get(route('customers.index'))
            ->assertOk()
            ->assertSee('得意先テスト')
            ->assertDontSee('仕入先テスト');
    }

    #[Test]
    public function the_list_aggregates_without_n_plus_one_queries(): void
    {
        $this->actingAsRole(RoleName::Staff);

        $this->createCustomersWithDeals(2);
        $this->get(route('customers.index'))->assertOk();   // 権限などのキャッシュを温める

        $baseline = $this->countQueries(fn () => $this->get(route('customers.index'))->assertOk());

        $this->createCustomersWithDeals(10);

        $scaled = $this->countQueries(fn () => $this->get(route('customers.index'))->assertOk());

        $this->assertSame(
            $baseline,
            $scaled,
            '顧客が増えてもクエリ本数は変わらない(金額はサブクエリで一緒に取得している)。',
        );
    }

    #[Test]
    public function the_customer_detail_shows_every_tab(): void
    {
        $this->actingAsRole(RoleName::Staff);

        $customer = $this->customer();
        PartnerContact::factory()->create(['partner_id' => $customer->id, 'name' => '山田 花子']);
        $deal = Deal::factory()->won()->create([
            'partner_id' => $customer->id,
            'title' => '基幹システム刷新',
            'amount_total' => 550000,
        ]);
        Activity::factory()->create([
            'partner_id' => $customer->id,
            'deal_id' => $deal->id,
            'note' => '初回訪問。課題をヒアリングした。',
        ]);

        $this->get(route('customers.show', $customer->id))
            ->assertOk()
            ->assertSee('テスト商事株式会社')
            // 概要タブの金額サマリ(税込)
            ->assertSee('累計売上(税込)')
            ->assertSee('進行中商談(税込)')
            ->assertSee('受注残(税込)')
            ->assertSee('550,000')
            // 各タブの中身
            ->assertSee('山田 花子')
            ->assertSee('基幹システム刷新')
            ->assertSee('初回訪問。課題をヒアリングした。');
    }

    #[Test]
    public function a_contact_can_be_added_from_the_detail_screen(): void
    {
        $this->actingAsRole(RoleName::Staff);

        $customer = $this->customer();

        $this->post(route('customers.contacts.store', $customer->id), [
            'name' => '鈴木 一郎',
            'department' => '情報システム部',
            'position' => '課長',
            'email' => 'suzuki@example.com',
            'phone' => '03-1234-5678',
            'is_active' => '1',
        ])->assertRedirect(route('customers.show', ['id' => $customer->id, 'tab' => 'contacts']));

        $contact = PartnerContact::query()->sole();

        $this->assertSame($customer->id, $contact->partner_id);
        $this->assertSame('鈴木 一郎', $contact->name);
        $this->assertSame('情報システム部', $contact->department);
        $this->assertTrue($contact->is_active);
    }

    #[Test]
    public function a_contact_can_be_updated_and_deactivated(): void
    {
        $this->actingAsRole(RoleName::Staff);

        $customer = $this->customer();
        $contact = PartnerContact::factory()->create([
            'partner_id' => $customer->id,
            'name' => '変更前',
        ]);

        // is_active を送らない = 無効化
        $this->put(route('customers.contacts.update', ['id' => $customer->id, 'contact' => $contact->id]), [
            'name' => '変更後',
            'position' => '部長',
        ])->assertRedirect(route('customers.show', ['id' => $customer->id, 'tab' => 'contacts']));

        $contact->refresh();

        $this->assertSame('変更後', $contact->name);
        $this->assertSame('部長', $contact->position);
        $this->assertFalse($contact->is_active);
    }

    #[Test]
    public function a_contact_of_another_customer_cannot_be_updated(): void
    {
        $this->actingAsRole(RoleName::Staff);

        $customer = $this->customer();
        $other = $this->customer('別の会社');
        $contact = PartnerContact::factory()->create(['partner_id' => $other->id, 'name' => '他社担当']);

        $this->put(route('customers.contacts.update', ['id' => $customer->id, 'contact' => $contact->id]), [
            'name' => '書き換え',
        ])->assertNotFound();

        $this->assertSame('他社担当', $contact->refresh()->name);
    }

    #[Test]
    public function contact_input_is_validated(): void
    {
        $this->actingAsRole(RoleName::Staff);

        $customer = $this->customer();

        $this->from(route('customers.show', $customer->id))
            ->post(route('customers.contacts.store', $customer->id), [
                'name' => '',
                'email' => 'not-an-email',
            ])
            ->assertSessionHasErrors(['name', 'email']);

        $this->assertSame(0, PartnerContact::query()->count());
    }

    #[Test]
    public function a_viewer_can_browse_but_cannot_manage_contacts(): void
    {
        $this->actingAsRole(RoleName::Viewer);

        $customer = $this->customer();
        $contact = PartnerContact::factory()->create(['partner_id' => $customer->id]);

        $this->get(route('customers.index'))->assertOk();
        $this->get(route('customers.export'))->assertOk();
        $this->get(route('customers.show', $customer->id))->assertOk();

        $this->post(route('customers.contacts.store', $customer->id), ['name' => 'x'])->assertForbidden();
        $this->put(route('customers.contacts.update', ['id' => $customer->id, 'contact' => $contact->id]), ['name' => 'x'])
            ->assertForbidden();
        $this->delete(route('customers.destroy', $customer->id))->assertForbidden();
    }

    #[Test]
    public function only_an_administrator_can_restore_a_deleted_customer(): void
    {
        $customer = $this->customer();

        $this->actingAsRole(RoleName::Staff);
        $this->delete(route('customers.destroy', $customer->id))
            ->assertRedirect(route('customers.index'));
        $this->assertSoftDeleted($customer);

        $this->post(route('customers.restore', $customer->id))->assertForbidden();

        $this->actingAsRole(RoleName::Admin);
        $this->post(route('customers.restore', $customer->id))
            ->assertRedirect(route('customers.index'));
        $this->assertNotSoftDeleted($customer->fresh());
    }

    private function createCustomersWithDeals(int $count): void
    {
        Partner::factory()
            ->count($count)
            ->create(['partner_type' => PartnerType::Customer])
            ->each(function (Partner $partner): void {
                Deal::factory()->won()->create(['partner_id' => $partner->id, 'amount_total' => 100000]);
                Deal::factory()->create(['partner_id' => $partner->id, 'amount_total' => 50000]);
            });
    }

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
