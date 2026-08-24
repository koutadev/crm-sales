<?php

namespace App\View\Components;

use App\Models\User;
use App\Support\Navigation\NavigationMenu;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\Component;
use Illuminate\View\View;

/**
 * 左サイドナビゲーション。
 *
 * メニューの中身は App\Support\Navigation\NavigationMenu が持つ。
 * 開閉状態は Alpine(appShell)が持ち、ここでは見た目だけを扱う。
 */
class AppSidebar extends Component
{
    public function __construct(private readonly NavigationMenu $menu) {}

    public function render(): View
    {
        /** @var User|null $user */
        $user = Auth::user();

        return view('components.app-sidebar', [
            'sections' => $this->menu->visibleSections($user),
        ]);
    }
}
