<?php

namespace Tests\Feature;

use App\Enums\EmploymentStatus;
use App\Enums\RoleName;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * 共通一覧基盤(検索・絞り込み・ソート・ページング・CSV・削除済み表示)の検証。
 *
 * 社員マスタを題材にしているが、実装はすべて App\Support\DataTable の共通処理。
 */
class DataTableTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsRole(RoleName $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role->value);

        $this->actingAs($user);

        return $user;
    }

    #[Test]
    public function it_shows_20_records_per_page(): void
    {
        $this->actingAsRole(RoleName::Admin);
        Employee::factory()->count(25)->create();

        $response = $this->get(route('masters.employees.index'));

        $response->assertOk();
        $response->assertSee('全 25 件');
        $this->assertCount(20, $response->viewData('table')->items());

        $this->assertCount(5, $this->get(route('masters.employees.index', ['page' => 2]))->viewData('table')->items());
    }

    #[Test]
    public function it_searches_by_code_name_and_email(): void
    {
        $this->actingAsRole(RoleName::Admin);

        $target = Employee::factory()->create(['name' => '検索 対象', 'email' => 'target@example.com']);
        Employee::factory()->count(3)->create();

        foreach ([$target->code, '検索 対象', 'target@example.com'] as $keyword) {
            $response = $this->get(route('masters.employees.index', ['q' => $keyword]));

            $this->assertCount(1, $response->viewData('table')->items(), "「{$keyword}」で 1 件に絞り込めること");
        }
    }

    #[Test]
    public function it_filters_by_employment_status_and_active_flag(): void
    {
        $this->actingAsRole(RoleName::Admin);

        Employee::factory()->count(3)->create();
        Employee::factory()->retired()->count(2)->create();

        $retired = $this->get(route('masters.employees.index', ['employment_status' => EmploymentStatus::Retired->value]));
        $this->assertCount(2, $retired->viewData('table')->items());

        $inactive = $this->get(route('masters.employees.index', ['is_active' => '0']));
        $this->assertCount(2, $inactive->viewData('table')->items());

        $active = $this->get(route('masters.employees.index', ['is_active' => '1']));
        $this->assertCount(3, $active->viewData('table')->items());
    }

    #[Test]
    public function it_sorts_by_updated_at_descending_by_default(): void
    {
        $this->actingAsRole(RoleName::Admin);

        $old = Employee::factory()->create();
        $new = Employee::factory()->create();

        $old->forceFill(['updated_at' => now()->subDay()])->saveQuietly();

        $items = $this->get(route('masters.employees.index'))->viewData('table')->items();

        $this->assertSame($new->id, $items->first()?->id);
    }

    #[Test]
    public function it_sorts_by_a_chosen_column_in_both_directions(): void
    {
        $this->actingAsRole(RoleName::Admin);
        Employee::factory()->count(3)->create();

        $asc = $this->get(route('masters.employees.index', ['sort' => 'code', 'direction' => 'asc']))
            ->viewData('table')->items()->pluck('code')->all();

        $desc = $this->get(route('masters.employees.index', ['sort' => 'code', 'direction' => 'desc']))
            ->viewData('table')->items()->pluck('code')->all();

        $this->assertSame(['EMP-0001', 'EMP-0002', 'EMP-0003'], $asc);
        $this->assertSame(array_reverse($asc), $desc);
    }

    #[Test]
    public function an_unknown_sort_column_falls_back_to_the_default(): void
    {
        $this->actingAsRole(RoleName::Admin);
        Employee::factory()->create();

        // SQL インジェクションや不正なカラム指定を弾く
        $state = $this->get(route('masters.employees.index', ['sort' => 'password', 'direction' => 'xxx']))
            ->viewData('table')->state;

        $this->assertSame('updated_at', $state->sort);
        $this->assertSame('desc', $state->direction);
    }

    #[Test]
    public function search_conditions_survive_navigating_away_and_back(): void
    {
        $this->actingAsRole(RoleName::Admin);

        Employee::factory()->count(3)->create();
        Employee::factory()->retired()->count(2)->create();

        // 1. 条件を指定して一覧を開く
        $this->get(route('masters.employees.index', ['employment_status' => EmploymentStatus::Retired->value]));

        // 2. 別画面へ移動する
        $this->get(route('dashboard'));

        // 3. 条件を付けずに一覧へ戻ると、前回の条件が復元される
        $restored = $this->get(route('masters.employees.index'));
        $this->assertCount(2, $restored->viewData('table')->items());

        // 4. reset を付けると条件が消える
        $reset = $this->get(route('masters.employees.index', ['reset' => 1]));
        $this->assertCount(5, $reset->viewData('table')->items());
    }

    #[Test]
    public function it_exports_all_matching_rows_as_utf8_bom_csv(): void
    {
        $this->actingAsRole(RoleName::Admin);

        Employee::factory()->count(25)->create();

        $response = $this->get(route('masters.employees.export'));

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('attachment; filename="employees_', $response->headers->get('content-disposition') ?? '');

        $csv = $response->streamedContent();

        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv, 'Excel 対策の UTF-8 BOM が必要');
        $this->assertStringContainsString('"社員コード","氏名"', $csv);

        // ヘッダー + 25 件(ページングされない)
        $this->assertSame(26, substr_count($csv, "\r\n"));
    }

    #[Test]
    public function the_csv_reflects_the_current_search_conditions(): void
    {
        $this->actingAsRole(RoleName::Admin);

        Employee::factory()->count(5)->create();
        Employee::factory()->retired()->count(2)->create();

        $csv = $this->get(route('masters.employees.export', [
            'employment_status' => EmploymentStatus::Retired->value,
        ]))->streamedContent();

        $this->assertSame(3, substr_count($csv, "\r\n"), 'ヘッダー + 退職者 2 件');
    }

    #[Test]
    public function csv_values_that_look_like_formulas_are_neutralized(): void
    {
        $this->actingAsRole(RoleName::Admin);

        Employee::factory()->create(['name' => '=1+1']);

        $csv = $this->get(route('masters.employees.export'))->streamedContent();

        $this->assertStringContainsString('"\'=1+1"', $csv, 'Excel で数式として実行されないようにする');
    }

    #[Test]
    public function deleted_records_are_hidden_from_everyone_by_default(): void
    {
        $this->actingAsRole(RoleName::Admin);

        $employee = Employee::factory()->create();
        $employee->delete();

        $this->assertCount(0, $this->get(route('masters.employees.index'))->viewData('table')->items());
    }

    #[Test]
    public function only_administrators_can_list_deleted_records(): void
    {
        $employee = Employee::factory()->create();
        $employee->delete();

        // 管理者は「削除済みのみ」で表示できる
        $this->actingAsRole(RoleName::Admin);
        $admin = $this->get(route('masters.employees.index', ['trashed' => 'only']));
        $this->assertCount(1, $admin->viewData('table')->items());
        $admin->assertSee('削除済みのみ');

        // 担当者は同じ URL でも削除済みを見られない
        $this->actingAsRole(RoleName::Staff);
        $staff = $this->get(route('masters.employees.index', ['trashed' => 'only']));
        $this->assertCount(0, $staff->viewData('table')->items());
        $staff->assertDontSee('削除済みのみ');
    }
}
