<?php

namespace App\Http\Controllers;

use App\Http\Requests\SavedViewRequest;
use App\Models\SavedView;
use App\Support\DataTable\SavedViewConditions;
use App\Support\Ui\Toast;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * 一覧の保存ビュー(マイビュー)の保存と削除。
 *
 * 呼び出しは一覧側(?view=<id>)で行うので、ここは作る / 消すだけ。
 * 自分のビューしか作れず、消せない。
 */
class SavedViewController extends Controller
{
    /**
     * 現在の絞り込み条件に名前を付けて保存する(同じ名前なら上書き)。
     */
    public function store(SavedViewRequest $request): RedirectResponse
    {
        $userId = (int) $request->user()?->id;

        /** @var array<array-key, mixed> $conditions */
        $conditions = $request->input('conditions', []);

        $view = SavedView::query()->updateOrCreate(
            [
                'user_id' => $userId,
                'table_key' => $request->string('table_key')->toString(),
                'name' => $request->string('name')->trim()->toString(),
            ],
            [
                'conditions' => SavedViewConditions::sanitize($conditions),
                'is_default' => $request->boolean('is_default'),
            ],
        );

        // 既定は 1 つだけ
        if ($view->is_default) {
            SavedView::query()
                ->ownedBy($userId)
                ->where('table_key', $view->table_key)
                ->whereKeyNot($view->id)
                ->update(['is_default' => false]);
        }

        return $this->backToList($request, $view)
            ->with(Toast::SESSION_KEY, Toast::success('ビュー「'.$view->name.'」を保存しました。'));
    }

    public function destroy(Request $request, SavedView $savedView): RedirectResponse
    {
        // 他人のビューは触れない
        abort_unless($savedView->user_id === $request->user()?->id, 404);

        $name = $savedView->name;
        $savedView->delete();

        return $this->backToList($request, null)
            ->with(Toast::SESSION_KEY, Toast::success('ビュー「'.$name.'」を削除しました。'));
    }

    /**
     * 元の一覧へ戻す。保存した直後はそのビューを適用した状態にする。
     */
    private function backToList(Request $request, ?SavedView $view): RedirectResponse
    {
        $target = (string) $request->string('redirect_to');

        if ($target === '' || ! str_starts_with($target, '/')) {
            return redirect()->back();
        }

        if ($view !== null) {
            $target = $target.(str_contains($target, '?') ? '&' : '?').'view='.$view->id;
        }

        return redirect()->to($target);
    }
}
