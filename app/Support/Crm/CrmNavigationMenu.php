<?php

namespace App\Support\Crm;

use App\Enums\PermissionName;
use App\Support\Navigation\NavigationMenu;
use App\Support\Navigation\NavItem;
use App\Support\Navigation\NavSection;

/**
 * CRM の左サイドナビ。
 *
 * 共通基盤のメニューに「営業」セクション(ダッシュボード・商談・顧客)を足し、
 * マスタには CRM 固有の税率を含める。AppServiceProvider で差し込んでいる。
 */
class CrmNavigationMenu extends NavigationMenu
{
    /**
     * @return list<NavSection>
     */
    public function sections(): array
    {
        return [
            new NavSection('営業', [
                new NavItem('ダッシュボード', 'dashboard', 'dashboard', PermissionName::DashboardView),
                new NavItem('商談', 'deals.index', 'deals', PermissionName::MasterView, 'deals.*'),
                new NavItem('顧客', 'customers.index', 'partners', PermissionName::MasterView, 'customers.*'),
            ]),

            // 個々のマスタはハブ(マスタ管理)から入る。
            // ナビには出さないが、現在地のハイライトとパンくずには使う。
            new NavSection('マスタ', [
                new NavItem('マスタ管理', 'masters.index', 'masters', PermissionName::MasterView, 'masters.index'),
                new NavItem('税率', 'masters.tax-rates.index', 'categories', PermissionName::MasterView, 'masters.tax-rates.*', hidden: true),
                new NavItem('売上目標', 'masters.sales-targets.index', 'dashboard', PermissionName::MasterView, 'masters.sales-targets.*', hidden: true),
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
}
