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
     * @param  PermissionName|null  $viewPermission  一覧を開くのに必要な権限(既定: master.view)
     * @param  PermissionName|null  $managePermission  登録・編集に必要な権限(既定: master.manage)
     */
    public function __construct(
        public readonly string $key,
        public readonly string $label,
        public readonly string $description,
        public readonly string $icon,
        public readonly string $routeName,
        public readonly string $modelClass,
        private readonly ?PermissionName $viewPermission = null,
        private readonly ?PermissionName $managePermission = null,
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
        return $this->viewPermission ?? PermissionName::MasterView;
    }

    /**
     * 登録・編集に必要な権限。
     */
    public function managePermission(): PermissionName
    {
        return $this->managePermission ?? PermissionName::MasterManage;
    }
}
