<?php

namespace App\Tables;

use App\Models\User;
use App\Support\DataTable\Column;
use App\Support\DataTable\TableDefinition;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * ユーザー一覧(ロール管理画面)の定義。
 *
 * users は業務マスタではないため CSV 出力と削除済み表示は持たない。
 */
class UserTable extends TableDefinition
{
    public function key(): string
    {
        return 'users';
    }

    public function routeName(): string
    {
        return 'users';
    }

    public function query(): Builder
    {
        return User::query()->with('roles:id,name');
    }

    public function columns(): array
    {
        return [
            new Column('name', '氏名', sortable: true),
            new Column('email', 'メールアドレス', sortable: true),
            new Column('roles', 'ロール'),
            new Column('created_at', '登録日時', sortable: true, wrap: false),
        ];
    }

    public function searchable(): array
    {
        return ['name', 'email'];
    }

    public function searchPlaceholder(): string
    {
        return '氏名・メールアドレスで検索';
    }

    public function exportable(): bool
    {
        return false;
    }

    public function defaultSort(): string
    {
        return 'created_at';
    }

    public function toCsvRow(Model $model): array
    {
        /** @var User $model */
        return [
            $model->name,
            $model->email,
            $model->getRoleNames()->implode(' / '),
            $model->created_at?->format('Y/m/d H:i'),
        ];
    }
}
