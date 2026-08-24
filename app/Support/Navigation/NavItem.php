<?php

namespace App\Support\Navigation;

use App\Enums\PermissionName;
use App\Models\User;
use Illuminate\Support\Facades\Route;

/**
 * 左サイドナビゲーションの項目 1 つぶん。
 *
 * ルート名と権限で定義し、URL・現在地判定はここに閉じ込める
 * (画面側でルート名やパスを直接書かないようにするため)。
 */
class NavItem
{
    /**
     * @param  string  $label  メニューに出す名称
     * @param  string  $routeName  遷移先のルート名
     * @param  string  $icon  アイコン名(resources/views/components/icon.blade.php)
     * @param  PermissionName|null  $permission  必要な権限。null なら誰でも見える
     * @param  string|null  $activePattern  現在地とみなすルート名のパターン(既定はルート名そのもの)
     * @param  bool  $hidden  ナビには出さない(現在地の判定とパンくずには使う)
     */
    public function __construct(
        public readonly string $label,
        public readonly string $routeName,
        public readonly string $icon = 'square',
        public readonly ?PermissionName $permission = null,
        public readonly ?string $activePattern = null,
        public readonly bool $hidden = false,
    ) {}

    public function url(): string
    {
        return route($this->routeName);
    }

    /**
     * いま開いている画面がこの項目かどうか。
     *
     * 登録・編集画面(masters.employees.create など)も同じ項目の現在地として扱えるよう、
     * パターン一致で判定する。
     */
    public function isActive(): bool
    {
        return request()->routeIs($this->activePattern ?? $this->routeName);
    }

    /**
     * このユーザーに見せてよい項目か。
     */
    public function isVisibleTo(?User $user): bool
    {
        if (! Route::has($this->routeName)) {
            return false;
        }

        if ($this->permission === null) {
            return true;
        }

        return $user?->can($this->permission->value) ?? false;
    }
}
