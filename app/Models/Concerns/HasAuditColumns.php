<?php

namespace App\Models\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

/**
 * created_by / updated_by を自動で記録する。
 *
 * テーブル側には `$table->auditColumns()` (AppServiceProvider で定義したマクロ) で
 * created_by / updated_by / timestamps / deleted_at をまとめて追加する。
 *
 * 明示的に値がセットされている場合は上書きしない(データ移行やバッチで指定したい場合に対応)。
 *
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property-read User|null $creator
 * @property-read User|null $updater
 */
trait HasAuditColumns
{
    public static function bootHasAuditColumns(): void
    {
        static::creating(function (Model $model): void {
            $userId = Auth::id();

            if ($userId === null) {
                return;
            }

            if ($model->getAttribute('created_by') === null) {
                $model->setAttribute('created_by', $userId);
            }

            if ($model->getAttribute('updated_by') === null) {
                $model->setAttribute('updated_by', $userId);
            }
        });

        static::updating(function (Model $model): void {
            $userId = Auth::id();

            if ($userId === null) {
                return;
            }

            // 明示的に updated_by を指定した更新はそのまま尊重する
            if (! $model->isDirty('updated_by')) {
                $model->setAttribute('updated_by', $userId);
            }
        });
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
