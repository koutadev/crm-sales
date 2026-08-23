<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

/**
 * 操作ログ。
 *
 * 業務モデルからの記録は {@see LogsActivity} が自動で行う。
 * 任意の操作を記録したい場合は {@see self::record()} を直接呼ぶ。
 *
 * 注意: このモデル自身は BaseModel を継承しない(ログのログを取らないため)。
 *
 * @property int $id
 * @property int|null $user_id
 * @property string $action
 * @property string|null $subject_type
 * @property int|null $subject_id
 * @property string|null $subject_label
 * @property array<string, mixed>|null $changes
 * @property string|null $ip_address
 * @property Carbon|null $created_at
 */
class ActivityLog extends Model
{
    /** 操作ログは更新しない(追記のみ)ため updated_at を持たない */
    public const UPDATED_AT = null;

    protected $fillable = [
        'user_id',
        'action',
        'subject_type',
        'subject_id',
        'subject_label',
        'changes',
        'ip_address',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'changes' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /**
     * 操作ログを 1 件記録する。
     *
     * @param  array<string, mixed>|null  $changes
     */
    public static function record(
        string $action,
        ?Model $subject = null,
        ?array $changes = null,
        ?string $subjectLabel = null,
        ?int $userId = null,
    ): self {
        return static::create([
            'user_id' => $userId ?? Auth::id(),
            'action' => $action,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'subject_label' => $subjectLabel,
            'changes' => $changes !== null && $changes !== [] ? $changes : null,
            'ip_address' => request()->ip(),
        ]);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * 操作内容の日本語ラベル。
     */
    public function actionLabel(): string
    {
        return __('activity.actions.'.$this->action);
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeNewestFirst(Builder $query): Builder
    {
        return $query->orderByDesc('created_at')->orderByDesc('id');
    }
}
