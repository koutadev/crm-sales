<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;

/**
 * 有効フラグ(is_active)の共通仕様。
 *
 * テーブル側には `$table->masterColumns()` で is_active を追加する。
 * 既定値(true)は DB 側の default に任せるため、モデルからは代入しない。
 *
 * 「無効」は「もう使わないが、過去データからは参照される」状態を表す。
 * 完全に消す場合は論理削除(deleted_at)を使う。
 *
 * @property bool $is_active
 */
trait HasActiveFlag
{
    public function initializeHasActiveFlag(): void
    {
        $this->mergeCasts(['is_active' => 'boolean']);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeInactive(Builder $query): Builder
    {
        return $query->where('is_active', false);
    }

    public function activeLabel(): string
    {
        return $this->is_active ? '有効' : '無効';
    }
}
