<?php

namespace App\Models\Concerns;

use App\Models\ActivityLog;
use Illuminate\Database\Eloquent\Model;

/**
 * モデルの作成 / 更新 / 削除 / 復元を activity_logs テーブルに記録する。
 *
 * カスタマイズしたい場合はモデル側で以下を上書きする。
 *
 *   // ログに残さない属性(パスワード等)
 *   protected array $activityLogExcept = ['secret_code'];
 *
 *   // 一覧に表示する見出し
 *   public function activityLogLabel(): ?string { return $this->code.' '.$this->name; }
 *
 * 一時的に記録を止めたい場合は Model::withoutActivityLog(fn () => ...) を使う。
 */
trait LogsActivity
{
    /** ログ記録を一時停止するか(インポート等の一括処理向け) */
    protected static bool $activityLogSuspended = false;

    public static function bootLogsActivity(): void
    {
        // self はこのトレイトを使っているモデルクラスに解決される
        static::created(function (self $model): void {
            $model->recordActivity('created', $model->activityLogAttributes());
        });

        static::updated(function (self $model): void {
            $model->recordActivity('updated', $model->activityLogAttributes(dirtyOnly: true));
        });

        static::deleted(function (self $model): void {
            $forceDeleted = method_exists($model, 'isForceDeleting') && $model->isForceDeleting();

            $model->recordActivity($forceDeleted ? 'force_deleted' : 'deleted');
        });

        // SoftDeletes を使っているモデルのみ restored イベントを持つ
        if (method_exists(static::class, 'restored')) {
            static::restored(function (self $model): void {
                $model->recordActivity('restored');
            });
        }
    }

    /**
     * 一括処理などで操作ログを記録したくない場合に使う。
     *
     * @template TReturn
     *
     * @param  callable(): TReturn  $callback
     * @return TReturn
     */
    public static function withoutActivityLog(callable $callback): mixed
    {
        $previous = static::$activityLogSuspended;
        static::$activityLogSuspended = true;

        try {
            return $callback();
        } finally {
            static::$activityLogSuspended = $previous;
        }
    }

    /**
     * 一覧に表示する対象の見出し。必要に応じてモデル側で上書きする。
     */
    public function activityLogLabel(): ?string
    {
        foreach (['name', 'title', 'code', 'label'] as $attribute) {
            $value = $this->getAttribute($attribute);

            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        return null;
    }

    /**
     * ログに残さない属性。
     *
     * @return list<string>
     */
    protected function activityLogExcept(): array
    {
        /** @var list<string> $except */
        $except = property_exists($this, 'activityLogExcept') ? $this->activityLogExcept : [];

        return array_values(array_unique(array_merge(
            // 監査カラムは「誰が」を user_id で持っているため値としては残さない。
            // これを含めると、復元時に updated_by だけが変わった更新ログが余分に出る。
            ['password', 'remember_token', 'created_at', 'updated_at', 'deleted_at', 'created_by', 'updated_by'],
            $except,
        )));
    }

    /**
     * ログに残す属性値を組み立てる。
     *
     * @return array<string, mixed>
     */
    protected function activityLogAttributes(bool $dirtyOnly = false): array
    {
        /** @var array<string, mixed> $attributes */
        $attributes = $dirtyOnly ? $this->getChanges() : $this->getAttributes();

        return collect($attributes)
            ->except($this->activityLogExcept())
            ->all();
    }

    /**
     * @param  array<string, mixed>|null  $changes
     */
    protected function recordActivity(string $action, ?array $changes = null): void
    {
        if (static::$activityLogSuspended || ! config('activity_log.enabled', true)) {
            return;
        }

        // 更新で実質的な変更が無い場合は記録しない
        if ($action === 'updated' && ($changes === null || $changes === [])) {
            return;
        }

        ActivityLog::record($action, $this, $changes, $this->activityLogLabel());
    }
}
