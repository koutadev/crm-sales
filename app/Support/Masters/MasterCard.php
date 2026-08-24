<?php

namespace App\Support\Masters;

use App\Enums\PermissionName;
use App\Models\BaseModel;

/**
 * マスタ管理ハブに並べるカード 1 枚ぶん。
 */
class MasterCard
{
    /**
     * @param  string  $key  件数を引くときの識別子(テーブル名を想定)
     * @param  string  $label  マスタ名
     * @param  string  $description  何のためのマスタかの一言
     * @param  string  $icon  resources/views/components/icon.blade.php のアイコン名
     * @param  string  $routeName  一覧のルート名プレフィックス(例: masters.employees)
     * @param  class-string<BaseModel>  $modelClass  件数を数えるモデル
     */
    public function __construct(
        public readonly string $key,
        public readonly string $label,
        public readonly string $description,
        public readonly string $icon,
        public readonly string $routeName,
        public readonly string $modelClass,
    ) {}

    public function indexUrl(): string
    {
        return route($this->routeName.'.index');
    }

    public function createUrl(): string
    {
        return route($this->routeName.'.create');
    }

    /**
     * 一覧を開くのに必要な権限。
     */
    public function viewPermission(): PermissionName
    {
        return PermissionName::MasterView;
    }

    /**
     * 登録・編集に必要な権限。
     */
    public function managePermission(): PermissionName
    {
        return PermissionName::MasterManage;
    }
}
