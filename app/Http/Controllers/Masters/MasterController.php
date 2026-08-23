<?php

namespace App\Http\Controllers\Masters;

use App\Http\Controllers\Controller;
use App\Models\BaseModel;
use App\Support\DataTable\CsvExporter;
use App\Support\DataTable\Table;
use App\Support\DataTable\TableBuilder;
use App\Support\DataTable\TableDefinition;
use App\Support\DataTable\TableState;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * マスタ画面の共通処理。
 *
 * 一覧(検索条件の保持・ページング・ソート)・CSV 出力・論理削除・復元は
 * すべてここに実装してあるため、各マスタは登録/編集フォームだけを書けばよい。
 *
 * 権限はルート側(MasterRoutes)で制御する。
 * 削除済みの表示と復元は管理者(admin ロール)限定。
 */
abstract class MasterController extends Controller
{
    /**
     * 一覧の定義。
     */
    abstract protected function definition(): TableDefinition;

    /**
     * ビューのディレクトリ(例: 'masters.employees')。
     */
    abstract protected function viewPath(): string;

    /**
     * 対象モデル。
     *
     * @return class-string<BaseModel>
     */
    abstract protected function modelClass(): string;

    /**
     * 画面に出す名称(例: '社員')。
     */
    abstract protected function resourceLabel(): string;

    public function index(Request $request): View
    {
        $table = Table::make($this->definition(), $request, $this->canManageDeleted($request));

        return view($this->viewPath().'.index', array_merge(
            $this->sharedViewData(),
            ['table' => $table],
        ));
    }

    /**
     * 現在の検索条件のまま CSV を出力する(ページングは無視して全件)。
     */
    public function export(Request $request): StreamedResponse
    {
        $definition = $this->definition();

        $state = TableState::resolve($request, $definition, $this->canManageDeleted($request));

        return (new CsvExporter($definition, new TableBuilder($definition, $state)))->download();
    }

    /**
     * 論理削除。
     */
    public function destroy(Request $request, int $id): RedirectResponse
    {
        $model = $this->modelClass()::query()->findOrFail($id);

        $model->delete();

        return redirect()
            ->route($this->definition()->routeName().'.index')
            ->with('status', $this->resourceLabel().'を削除しました。');
    }

    /**
     * 削除済みデータの復元(管理者のみ)。
     */
    public function restore(Request $request, int $id): RedirectResponse
    {
        abort_unless($this->canManageDeleted($request), 403);

        $model = $this->modelClass()::query()->onlyTrashed()->findOrFail($id);

        $model->restore();

        return redirect()
            ->route($this->definition()->routeName().'.index')
            ->with('status', $this->resourceLabel().'を復元しました。');
    }

    /**
     * 削除済みの表示・復元ができるユーザーか。
     *
     * 今は「管理者ロールかどうか」で判定している。マスタ単位に権限を分ける場合は
     * ここを専用のパーミッション判定に差し替える。
     */
    protected function canManageDeleted(Request $request): bool
    {
        $user = $request->user();

        return $user !== null && $user->isAdmin();
    }

    /**
     * 一覧・フォーム共通でビューに渡すデータ。
     *
     * @return array<string, mixed>
     */
    protected function sharedViewData(): array
    {
        return [
            'resourceLabel' => $this->resourceLabel(),
            'routeName' => $this->definition()->routeName(),
        ];
    }

    /**
     * 保存後のリダイレクト先。検索条件を保持したまま一覧に戻る。
     */
    protected function redirectToIndex(string $message): RedirectResponse
    {
        return redirect()
            ->route($this->definition()->routeName().'.index')
            ->with('status', $message);
    }
}
