<?php

namespace App\Models;

use App\Models\Concerns\HasActiveFlag;
use App\Models\Concerns\HasAuditColumns;
use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * 業務テーブル用の基底モデル。
 *
 * 以下の共通仕様がまとめて有効になる。
 *
 *   - 論理削除 (deleted_at)
 *   - 有効フラグ (is_active) と active() / inactive() スコープ
 *   - 作成者 / 更新者の自動記録 (created_by / updated_by)
 *   - 操作ログの自動記録 (activity_logs)
 *
 * 対応するマイグレーションでは `$table->masterColumns()` を呼ぶこと。
 *
 *   Schema::create('employees', function (Blueprint $table) {
 *       $table->id();
 *       $table->string('code')->unique();
 *       $table->string('name');
 *       $table->masterColumns();  // is_active / created_by / updated_by / timestamps / deleted_at
 *   });
 *
 * 有効フラグが不要なテーブルは `$table->auditColumns()` を使う
 * (is_active スコープを呼ばなければモデル側はそのままで問題ない)。
 *
 * 共通仕様が不要なテーブル(中間テーブル等)は素の Model を継承すればよい。
 *
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 *
 * @phpstan-consistent-constructor
 */
abstract class BaseModel extends Model
{
    use HasActiveFlag;
    use HasAuditColumns;
    use LogsActivity;
    use SoftDeletes;
}
