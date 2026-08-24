<?php

namespace App\Http\Controllers\Crm;

use App\Http\Controllers\Controller;
use App\Http\Requests\Crm\DealActivityRequest;
use App\Models\Deal;
use Illuminate\Http\RedirectResponse;

/**
 * 商談詳細から活動履歴を追加する(インライン)。
 *
 * 活動は顧客にも必ず紐づくため、商談の顧客を自動でセットする。
 */
class DealActivityController extends Controller
{
    public function store(DealActivityRequest $request, int $dealId): RedirectResponse
    {
        $deal = Deal::query()->findOrFail($dealId);

        $deal->activities()->create($request->validated() + [
            'partner_id' => $deal->partner_id,
        ]);

        return redirect()
            ->route('deals.show', $deal->id)
            ->with('status', '活動履歴を追加しました。');
    }
}
