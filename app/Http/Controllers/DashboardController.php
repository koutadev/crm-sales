<?php

namespace App\Http\Controllers;

use App\Enums\EmploymentStatus;
use App\Enums\PartnerType;
use App\Enums\PermissionName;
use App\Models\ActivityLog;
use App\Models\Employee;
use App\Models\Partner;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Support\Dashboard\Chart;
use App\Support\Dashboard\Kpi;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * ダッシュボードの「枠」。
 *
 * ここで表示しているのは共通マスタの実データを使った例であり、
 * 各業務システムでは kpis() / charts() / recentActivities() の中身を差し替えて使う。
 * ビュー側（dashboard.blade.php）は「KPI カードの配列」「グラフの配列」を受け取るだけなので、
 * 内容を入れ替えてもレイアウトは変更不要。
 *
 * 権限を持たないユーザーには、そのブロックごと表示しない。
 */
class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        $canViewMasters = $request->user()?->can(PermissionName::MasterView->value) ?? false;
        $canViewLogs = $request->user()?->can(PermissionName::ActivityLogView->value) ?? false;

        return view('dashboard', [
            'kpis' => $canViewMasters ? $this->kpis() : [],
            'charts' => $canViewMasters ? $this->charts() : [],
            'recentActivities' => $canViewLogs ? $this->recentActivities() : null,
        ]);
    }

    /**
     * KPI カード。
     *
     * @return list<Kpi>
     */
    private function kpis(): array
    {
        $employeeCount = Employee::query()->count();
        $activeEmployees = Employee::query()
            ->where('employment_status', EmploymentStatus::Active)
            ->count();

        return [
            new Kpi(
                label: '社員',
                value: $employeeCount,
                unit: '名',
                href: route('masters.employees.index'),
                note: sprintf('うち在籍 %s 名', number_format($activeEmployees)),
            ),
            new Kpi(
                label: '取引先',
                value: Partner::query()->count(),
                unit: '社',
                href: route('masters.partners.index'),
                note: sprintf('得意先 %s 社', number_format(Partner::query()->customers()->count())),
            ),
            new Kpi(
                label: '商品',
                value: Product::query()->count(),
                unit: '件',
                href: route('masters.products.index'),
                note: sprintf('分類 %s 件', number_format(ProductCategory::query()->count())),
            ),
            new Kpi(
                label: '有効なマスタ',
                value: Employee::query()->active()->count()
                    + Partner::query()->active()->count()
                    + Product::query()->active()->count(),
                unit: '件',
                note: 'is_active が有効なデータの合計',
            ),
        ];
    }

    /**
     * グラフ。
     *
     * @return list<Chart>
     */
    private function charts(): array
    {
        return [
            Chart::doughnut('partner-type', '取引先区分の内訳', $this->partnerTypeBreakdown()),
            Chart::bar('product-category', '商品分類別の件数', $this->productCategoryBreakdown(), '商品件数'),
        ];
    }

    /**
     * @return array<string, int>
     */
    private function partnerTypeBreakdown(): array
    {
        /** @var Collection<string, int> $counts */
        $counts = Partner::query()
            ->selectRaw('partner_type, count(*) as aggregate')
            ->groupBy('partner_type')
            ->pluck('aggregate', 'partner_type');

        $breakdown = [];

        // 0 件の区分も 0 として並べ、凡例の順序を安定させる
        foreach (PartnerType::cases() as $type) {
            $breakdown[$type->label()] = (int) ($counts[$type->value] ?? 0);
        }

        return $breakdown;
    }

    /**
     * @return array<string, int>
     */
    private function productCategoryBreakdown(): array
    {
        $categories = ProductCategory::query()
            ->withCount('products')
            ->orderBy('code')
            ->get();

        $breakdown = [];

        foreach ($categories as $category) {
            $breakdown[$category->name] = (int) $category->getAttribute('products_count');
        }

        $uncategorized = Product::query()->whereNull('product_category_id')->count();

        if ($uncategorized > 0) {
            $breakdown['未分類'] = $uncategorized;
        }

        return $breakdown;
    }

    /**
     * 最近の操作ログ。
     *
     * @return Collection<int, ActivityLog>
     */
    private function recentActivities(): Collection
    {
        return ActivityLog::query()
            ->with('user:id,name')
            ->newestFirst()
            ->limit(8)
            ->get();
    }
}
