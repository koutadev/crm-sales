<?php

namespace App\Http\Controllers\Crm;

use App\Http\Controllers\Masters\MasterController;
use App\Models\Partner;
use App\Support\Crm\CustomerSummary;
use App\Support\Dashboard\Kpi;
use App\Support\DataTable\TableDefinition;
use App\Tables\CustomerTable;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * 顧客(会社)管理。
 *
 * 一覧・CSV・論理削除・復元は共通のマスタ基盤(MasterController)をそのまま使い、
 * ここでは「詳細(タブ構成)」だけを足している。
 * 取引先マスタ(masters.partners)が全社共通の台帳なのに対し、
 * こちらは得意先を営業視点で見るための画面。
 */
class CustomerController extends MasterController
{
    protected function definition(): TableDefinition
    {
        return new CustomerTable;
    }

    protected function viewPath(): string
    {
        return 'crm.customers';
    }

    protected function modelClass(): string
    {
        return Partner::class;
    }

    protected function resourceLabel(): string
    {
        return '顧客';
    }

    /**
     * 顧客詳細(概要 / 担当者 / 商談 / 活動)。
     */
    public function show(Request $request, int $id): View
    {
        $customer = Partner::query()
            // 削除済みを見られるユーザー(管理者)は、削除済みの顧客も開ける
            ->when($this->canManageDeleted($request), fn ($query) => $query->withTrashed())
            ->findOrFail($id);

        $summary = CustomerSummary::for($customer);

        return view($this->viewPath().'.show', array_merge($this->sharedViewData(), [
            'customer' => $customer,
            'summary' => $summary,
            'contacts' => $customer->contacts()->orderBy('name')->get(),
            'deals' => $customer->deals()
                ->with(['employee:id,name', 'partnerContact:id,name'])
                ->orderByDesc('id')
                ->limit(100)
                ->get(),
            'activities' => $customer->activities()
                ->with(['employee:id,name', 'deal:id,code,title'])
                ->orderByDesc('activity_at')
                ->limit(100)
                ->get(),
            'kpis' => $this->kpisFor($summary),
            'tab' => $this->currentTab($request),
        ]));
    }

    /**
     * 概要タブ上部の金額サマリ(ダッシュボードの KPI カードを再利用する)。
     *
     * @return list<Kpi>
     */
    private function kpisFor(CustomerSummary $summary): array
    {
        return [
            new Kpi(
                label: '累計売上(税込)',
                value: $summary->wonTotal,
                unit: '円',
                note: '受注済み '.number_format($summary->wonCount).' 件',
            ),
            new Kpi(
                label: '進行中商談(税込)',
                value: $summary->openTotal,
                unit: '円',
                note: '進行中 '.number_format($summary->openCount).' 件',
            ),
            new Kpi(
                label: '受注残(税込)',
                value: $summary->backlogTotal,
                unit: '円',
                note: '受注済みのうち予定クローズ日が未到来',
            ),
            new Kpi(
                label: '商談数',
                value: $summary->dealCount,
                unit: '件',
                note: '失注を含む全件',
            ),
        ];
    }

    /**
     * 表示するタブ。入力値は定義済みのものだけ受け付ける。
     */
    private function currentTab(Request $request): string
    {
        $tab = (string) $request->string('tab');

        return in_array($tab, ['overview', 'contacts', 'deals', 'activities'], true) ? $tab : 'overview';
    }
}
