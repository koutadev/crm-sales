<?php

namespace App\Models;

use Database\Factories\SavedViewFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;

/**
 * 一覧の保存ビュー(マイビュー)。
 *
 * 条件は URL のクエリと同じ形([パラメータ名 => 値])で持つ。
 * 呼び出しは一覧に ?view=<id> を付けるだけで、条件の解釈は
 * 通常のリクエストとまったく同じ経路(TableState)を通る。
 *
 * @property int $id
 * @property int $user_id
 * @property string $table_key
 * @property string $name
 * @property array<string, string> $conditions
 * @property bool $is_default
 */
class SavedView extends Model
{
    /** @use HasFactory<SavedViewFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'table_key',
        'name',
        'conditions',
        'is_default',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'conditions' => 'array',
            'is_default' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * 自分のビューだけに絞る。
     *
     * @param  Builder<SavedView>  $query
     * @return Builder<SavedView>
     */
    public function scopeOwnedBy(Builder $query, ?int $userId): Builder
    {
        // 未ログイン(実際には起きない)なら 1 件も返さない
        return $query->where('user_id', $userId ?? 0);
    }

    /**
     * ある一覧のビューを、既定 → 名前順で返す。
     *
     * @return Collection<int, SavedView>
     */
    public static function forTable(?int $userId, string $tableKey): Collection
    {
        return self::query()
            ->ownedBy($userId)
            ->where('table_key', $tableKey)
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get();
    }
}
