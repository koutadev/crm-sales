<?php

namespace App\Support\Navigation;

use App\Enums\PermissionName;
use App\Models\User;

/**
 * 左サイドナビゲーションの構成。
 *
 * 画面が増えてもここだけを直せばよいように、メニューの定義を 1 か所に集めている。
 * パンくずもこの定義から自動で組み立てるため、各画面で書く必要はない。
 *
 * 業務システムごとにメニューを差し替える場合は、このクラスを継承して sections() を
 * 上書きし、AppServiceProvider でコンテナに差し込む。
 *
 *   // 例: CRM 側のサービスプロバイダ
 *   $this->app->bind(NavigationMenu::class, CrmNavigationMenu::class);
 */
class NavigationMenu
{
    /**
     * メニューの定義。
     *
     * @return list<NavSection>
     */
    public function sections(): array
    {
        return [
            new NavSection('', [
                new NavItem(
                    label: 'ダッシュボード',
                    routeName: 'dashboard',
                    icon: 'dashboard',
                    permission: PermissionName::DashboardView,
                ),
            ]),

            // 個々のマスタはハブ(マスタ管理)から入る。
            // ナビには出さないが、現在地のハイライトとパンくずのために定義は残す。
            new NavSection('マスタ', [
                new NavItem('マスタ管理', 'masters.index', 'masters', PermissionName::MasterView, 'masters.index'),
                new NavItem('組織', 'masters.organizations.index', 'departments', PermissionName::MasterView, 'masters.organizations.*', hidden: true),
                new NavItem('社員', 'masters.employees.index', 'employees', PermissionName::MasterView, 'masters.employees.*', hidden: true),
                new NavItem('取引先', 'masters.partners.index', 'partners', PermissionName::MasterView, 'masters.partners.*', hidden: true),
                new NavItem('商品', 'masters.products.index', 'products', PermissionName::MasterView, 'masters.products.*', hidden: true),
                new NavItem('部署', 'masters.departments.index', 'departments', PermissionName::MasterView, 'masters.departments.*', hidden: true),
                new NavItem('役職', 'masters.positions.index', 'positions', PermissionName::MasterView, 'masters.positions.*', hidden: true),
                new NavItem('商品分類', 'masters.product-categories.index', 'categories', PermissionName::MasterView, 'masters.product-categories.*', hidden: true),
            ]),

            new NavSection('管理', [
                new NavItem('ユーザー管理', 'users.index', 'user-cog', PermissionName::UserManage, 'users.*'),
                new NavItem('操作ログ', 'activity-logs.index', 'history', PermissionName::ActivityLogView, 'activity-logs.*'),
            ]),
        ];
    }

    /**
     * 権限で絞ったセクション(項目が 1 つも無いセクションは落とす)。
     *
     * @return list<NavSection>
     */
    public function visibleSections(?User $user): array
    {
        $sections = [];

        foreach ($this->sections() as $section) {
            $items = $section->visibleItems($user);

            if ($items !== []) {
                $sections[] = new NavSection($section->label, $items);
            }
        }

        return $sections;
    }

    /**
     * いま開いている画面に対応するメニュー項目。
     */
    public function currentItem(): ?NavItem
    {
        foreach ($this->sections() as $section) {
            foreach ($section->items as $item) {
                if ($item->isActive()) {
                    return $item;
                }
            }
        }

        return null;
    }

    /**
     * いま開いている画面が属するセクション。
     */
    public function currentSection(): ?NavSection
    {
        foreach ($this->sections() as $section) {
            foreach ($section->items as $item) {
                if ($item->isActive()) {
                    return $section;
                }
            }
        }

        return null;
    }

    /**
     * パンくず。メニューの定義から自動で組み立てる。
     *
     * 例: ダッシュボード > マスタ > 社員
     * 末尾に画面固有の見出し($trail)を足せる(例: … > 社員 > 新規登録)。
     *
     * @return list<array{label: string, url: string|null}>
     */
    public function breadcrumbs(?User $user, ?string $trail = null): array
    {
        $crumbs = [];

        $home = $this->homeItem();

        if ($home !== null && $home->isVisibleTo($user)) {
            $crumbs[] = ['label' => $home->label, 'url' => $home->isActive() ? null : $home->url()];
        }

        $section = $this->currentSection();
        $current = $this->currentItem();

        if ($section !== null && $section->hasHeading()) {
            // セクションの入口(ハブなど)があればリンクにする
            $entry = $section->entryItem($user);

            $crumbs[] = [
                'label' => $section->label,
                'url' => $entry !== null && ! $entry->isActive() ? $entry->url() : null,
            ];
        }

        if ($current !== null && $current !== $home) {
            $crumbs[] = ['label' => $current->label, 'url' => $trail === null ? null : $current->url()];
        }

        if ($trail !== null) {
            $crumbs[] = ['label' => $trail, 'url' => null];
        }

        return $crumbs;
    }

    /**
     * パンくずの起点にする項目(通常はダッシュボード)。
     */
    protected function homeItem(): ?NavItem
    {
        return $this->sections()[0]->items[0] ?? null;
    }
}
