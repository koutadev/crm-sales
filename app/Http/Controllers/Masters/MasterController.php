<?php

namespace App\Http\Controllers\Masters;

use App\Enums\PermissionName;
use App\Http\Controllers\Controller;
use App\Models\BaseModel;
use App\Support\DataTable\CsvExporter;
use App\Support\DataTable\Table;
use App\Support\DataTable\TableBuilder;
use App\Support\DataTable\TableDefinition;
use App\Support\DataTable\TableState;
use App\Support\Ui\Toast;
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

    /**
     * モーダルの詳細に出す項目。
     *
     * 空を返すと詳細モーダルを使わない一覧として扱う(行クリックの導線を出さない)。
     *
     * @return array<string, string|null> [見出し => 値]
     */
    protected function detailRows(BaseModel $record): array
    {
        return [];
    }

    /**
     * 登録・編集フォームに渡すデータ(選択肢など)。
     *
     * フルページのフォームとモーダル編集で共有する。
     *
     * @return array<string, mixed>
     */
    protected function formData(BaseModel $record): array
    {
        return $this->sharedViewData();
    }

    /**
     * 入力項目のビュー(フルページとモーダルで共有する部分)。
     */
    protected function fieldsView(): string
    {
        return $this->viewPath().'.fields';
    }

    public function index(Request $request): View
    {
        $table = Table::make($this->definition(), $request, $this->canManageDeleted($request));

        return view($this->viewPath().'.index', array_merge(
            $this->sharedViewData(),
            [
                'table' => $table,
                // バリデーションエラーで戻ってきたときは、その行の詳細を開いた状態で描画する
                'initialDetail' => $this->initialDetail(),
            ],
        ));
    }

    /**
     * 一覧の行クリックで開くモーダルの中身(HTML の断片)。
     */
    public function detail(Request $request, int $id): View
    {
        $record = $this->modelClass()::query()
            ->when($this->canManageDeleted($request), fn ($query) => $query->withTrashed())
            ->findOrFail($id);

        // 詳細の定義が無い一覧では使わない
        abort_if($this->detailRows($record) === [], 404);

        return view('masters._detail', $this->detailData($record));
    }

    /**
     * 直前の送信がモーダルの編集フォームだった場合の、描き直す詳細。
     *
     * @return array<string, mixed>|null
     */
    protected function initialDetail(): ?array
    {
        $id = old('_modal_record');

        if (! is_numeric($id)) {
            return null;
        }

        $record = $this->modelClass()::query()->withTrashed()->find((int) $id);

        return $record === null ? null : $this->detailData($record);
    }

    /**
     * 詳細モーダルに渡すデータ。
     *
     * @return array<string, mixed>
     */
    protected function detailData(BaseModel $record): array
    {
        return array_merge($this->sharedViewData(), $this->formData($record), [
            'record' => $record,
            'rows' => $this->detailRows($record),
            'fieldsView' => $this->fieldsView(),
            'canManage' => request()->user()?->can(PermissionName::MasterManage->value) ?? false,
            'detailTitle' => $this->detailTitle($record),
        ]);
    }

    /**
     * モーダルの見出し。
     */
    protected function detailTitle(BaseModel $record): string
    {
        $name = $record->getAttribute('name');

        return is_string($name) && $name !== ''
            ? $this->resourceLabel().' — '.$name
            : $this->resourceLabel().'の詳細';
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
            ->with(Toast::SESSION_KEY, Toast::success($this->resourceLabel().'を削除しました。'));
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
            ->with(Toast::SESSION_KEY, Toast::success($this->resourceLabel().'を復元しました。'));
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
            ->with(Toast::SESSION_KEY, Toast::success($message));
    }
}
