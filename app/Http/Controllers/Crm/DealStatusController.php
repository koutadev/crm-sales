<?php

namespace App\Http\Controllers\Crm;

use App\Enums\DealStatus;
use App\Http\Controllers\Controller;
use App\Models\Deal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * カンバンでカードを別の列へ動かしたときの、ステータスだけの更新。
 *
 * 受注日の扱いは登録・編集画面と同じ規則にそろえる。
 *   - 受注へ移す → 受注日が空なら今日を入れる(明細が 1 件も無ければ受注にできない)
 *   - 受注から出す → 受注日を消す
 *
 * 金額(amount_total)は明細から計算されるものなので、ここでは触らない。
 */
class DealStatusController extends Controller
{
    public function update(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::enum(DealStatus::class)],
        ]);

        $deal = Deal::query()->withCount('items')->findOrFail($id);
        $status = DealStatus::from((string) $validated['status']);

        if ($status === $deal->status) {
            return response()->json(['message' => 'ステータスは変わっていません。']);
        }

        if ($status->isWon() && $deal->items_count === 0) {
            return response()->json(
                ['message' => '受注にするには、明細を 1 件以上登録してください。'],
                422,
            );
        }

        $deal->update([
            'status' => $status,
            'ordered_at' => $status->isWon()
                ? ($deal->ordered_at?->toDateString() ?? now()->toDateString())
                : null,
        ]);

        return response()->json([
            'message' => '商談 '.$deal->code.' を「'.$status->label().'」に変更しました。',
        ]);
    }
}
