<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\Fixtures\TestItem;
use Tests\TestCase;

/**
 * 業務テーブル共通仕様(created_by / updated_by / 論理削除)の検証。
 */
class AuditColumnsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // $table->auditColumns() マクロ自体もここで検証している
        Schema::create('test_items', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->auditColumns();
        });
    }

    #[Test]
    public function audit_columns_macro_creates_the_expected_columns(): void
    {
        foreach (['created_by', 'updated_by', 'created_at', 'updated_at', 'deleted_at'] as $column) {
            $this->assertTrue(
                Schema::hasColumn('test_items', $column),
                "auditColumns() が {$column} を作成していません。",
            );
        }
    }

    #[Test]
    public function master_columns_macro_adds_the_active_flag_on_top_of_the_audit_columns(): void
    {
        Schema::create('test_masters', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->masterColumns();
        });

        foreach (['is_active', 'created_by', 'updated_by', 'created_at', 'updated_at', 'deleted_at'] as $column) {
            $this->assertTrue(
                Schema::hasColumn('test_masters', $column),
                "masterColumns() が {$column} を作成していません。",
            );
        }
    }

    #[Test]
    public function the_active_scopes_filter_by_the_flag(): void
    {
        Employee::factory()->count(2)->create();
        Employee::factory()->retired()->create();

        $this->assertSame(2, Employee::query()->active()->count());
        $this->assertSame(1, Employee::query()->inactive()->count());
    }

    #[Test]
    public function it_records_the_logged_in_user_as_creator_and_updater(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        $item = TestItem::create(['name' => '検証データ']);

        $this->assertSame($user->id, $item->created_by);
        $this->assertSame($user->id, $item->updated_by);
        $this->assertTrue($item->creator->is($user));
        $this->assertTrue($item->updater->is($user));
    }

    #[Test]
    public function it_updates_only_the_updater_on_update(): void
    {
        $creator = User::factory()->create();
        $editor = User::factory()->create();

        $this->actingAs($creator);
        $item = TestItem::create(['name' => '検証データ']);

        $this->actingAs($editor);
        $item->update(['name' => '変更後']);

        $item->refresh();

        $this->assertSame($creator->id, $item->created_by, '作成者は変わってはいけません。');
        $this->assertSame($editor->id, $item->updated_by);
    }

    #[Test]
    public function it_leaves_audit_columns_null_when_no_user_is_authenticated(): void
    {
        $item = TestItem::create(['name' => 'バッチ処理で作成']);

        $this->assertNull($item->created_by);
        $this->assertNull($item->updated_by);
    }

    #[Test]
    public function it_soft_deletes_instead_of_removing_the_row(): void
    {
        $item = TestItem::create(['name' => '削除対象']);

        $item->delete();

        $this->assertSoftDeleted($item);
        $this->assertNull(TestItem::find($item->id));
        $this->assertNotNull(TestItem::withTrashed()->find($item->id));
    }
}
