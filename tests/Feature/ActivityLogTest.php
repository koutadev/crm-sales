<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\Fixtures\TestItem;
use Tests\TestCase;

/**
 * 操作ログ(誰が何を作成 / 更新 / 削除したか)の検証。
 */
class ActivityLogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('test_items', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->auditColumns();
        });
    }

    #[Test]
    public function it_logs_creation_with_the_acting_user(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        $item = TestItem::create(['name' => '新規データ']);

        $log = ActivityLog::where('subject_type', TestItem::class)->sole();

        $this->assertSame('created', $log->action);
        $this->assertSame($user->id, $log->user_id);
        $this->assertSame($item->id, $log->subject_id);
        $this->assertSame('新規データ', $log->subject_label);
        $this->assertSame('新規データ', $log->changes['name']);
    }

    #[Test]
    public function it_logs_updates_with_only_the_changed_attributes(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $item = TestItem::create(['name' => '変更前']);
        $item->update(['name' => '変更後']);

        $log = ActivityLog::where('subject_type', TestItem::class)
            ->where('action', 'updated')
            ->sole();

        $this->assertSame(['name' => '変更後'], $log->changes);
    }

    #[Test]
    public function it_does_not_log_an_update_that_changes_nothing(): void
    {
        $item = TestItem::create(['name' => '変更なし']);

        $item->update(['name' => '変更なし']);

        $this->assertSame(
            0,
            ActivityLog::where('subject_type', TestItem::class)->where('action', 'updated')->count(),
        );
    }

    #[Test]
    public function it_logs_soft_delete_and_restore(): void
    {
        $item = TestItem::create(['name' => '削除対象']);

        $item->delete();
        $item->restore();

        $actions = ActivityLog::where('subject_type', TestItem::class)
            ->orderBy('id')
            ->pluck('action')
            ->all();

        $this->assertSame(['created', 'deleted', 'restored'], $actions);
    }

    #[Test]
    public function it_distinguishes_force_delete_from_soft_delete(): void
    {
        $item = TestItem::create(['name' => '完全削除対象']);

        $item->forceDelete();

        $this->assertSame(
            1,
            ActivityLog::where('subject_type', TestItem::class)->where('action', 'force_deleted')->count(),
        );
    }

    #[Test]
    public function it_can_suspend_logging_for_bulk_operations(): void
    {
        TestItem::withoutActivityLog(function (): void {
            TestItem::create(['name' => '一括取込1']);
            TestItem::create(['name' => '一括取込2']);
        });

        $this->assertSame(0, ActivityLog::where('subject_type', TestItem::class)->count());

        // 抑止は一時的なもので、後続の操作は再び記録される
        TestItem::create(['name' => '通常作成']);

        $this->assertSame(1, ActivityLog::where('subject_type', TestItem::class)->count());
    }

    #[Test]
    public function it_never_logs_password_attributes(): void
    {
        $user = User::factory()->create();

        $log = ActivityLog::where('subject_type', User::class)->where('action', 'created')->sole();

        $this->assertArrayNotHasKey('password', $log->changes ?? []);
        $this->assertArrayNotHasKey('remember_token', $log->changes ?? []);
        $this->assertSame($user->name, $log->subject_label);
    }

    #[Test]
    public function it_logs_login_and_logout(): void
    {
        $user = User::factory()->create();

        $this->post(route('login'), [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->post(route('logout'));

        $this->assertSame(1, ActivityLog::where('action', 'logged_in')->where('user_id', $user->id)->count());
        $this->assertSame(1, ActivityLog::where('action', 'logged_out')->where('user_id', $user->id)->count());
    }
}
