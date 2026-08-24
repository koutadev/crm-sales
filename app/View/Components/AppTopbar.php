<?php

namespace App\View\Components;

use App\Models\User;
use App\Support\Navigation\NavigationMenu;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\Component;
use Illuminate\View\View;

/**
 * 画面上部のバー(パンくず + ユーザーメニュー)。
 *
 * パンくずはメニューの定義から自動で組み立てる。
 * 画面固有の末尾(例: 新規登録)を足したい場合は、レイアウトに breadcrumb スロットを渡す。
 */
class AppTopbar extends Component
{
    public function __construct(
        private readonly NavigationMenu $menu,
        public readonly ?string $trail = null,
    ) {}

    public function render(): View
    {
        /** @var User|null $user */
        $user = Auth::user();

        return view('components.app-topbar', [
            'breadcrumbs' => $this->menu->breadcrumbs($user, $this->trail),
            'user' => $user,
        ]);
    }
}
