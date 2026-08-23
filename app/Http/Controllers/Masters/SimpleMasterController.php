<?php

namespace App\Http\Controllers\Masters;

use App\Http\Requests\Masters\SimpleMasterRequest;
use App\Models\BaseModel;
use App\Support\DataTable\TableDefinition;
use App\Tables\SimpleMasterTable;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * 「コード + 名称」だけのサブマスタ(部署 / 役職 / 商品分類)の共通コントローラ。
 *
 * 画面は resources/views/masters/simple/ を 3 マスタで共有する。
 */
abstract class SimpleMasterController extends MasterController
{
    /**
     * コード列の見出し(例: '部署コード')。
     */
    abstract protected function codeLabel(): string;

    /**
     * 名称列の見出し(例: '部署名')。
     */
    abstract protected function nameLabel(): string;

    /**
     * 一覧の識別子 兼 URI(例: 'departments')。
     */
    abstract protected function resourceKey(): string;

    protected function definition(): TableDefinition
    {
        return new SimpleMasterTable(
            modelClass: $this->modelClass(),
            key: $this->resourceKey(),
            routeName: 'masters.'.$this->resourceKey(),
            codeLabel: $this->codeLabel(),
            nameLabel: $this->nameLabel(),
        );
    }

    protected function viewPath(): string
    {
        return 'masters.simple';
    }

    public function create(): View
    {
        $modelClass = $this->modelClass();

        return view($this->viewPath().'.form', $this->formData(new $modelClass));
    }

    public function store(SimpleMasterRequest $request): RedirectResponse
    {
        $this->modelClass()::create($request->validated());

        return $this->redirectToIndex($this->resourceLabel().'を登録しました。');
    }

    public function edit(int $id): View
    {
        $record = $this->modelClass()::query()->findOrFail($id);

        return view($this->viewPath().'.form', $this->formData($record));
    }

    public function update(SimpleMasterRequest $request, int $id): RedirectResponse
    {
        $this->modelClass()::query()->findOrFail($id)->update($request->validated());

        return $this->redirectToIndex($this->resourceLabel().'を更新しました。');
    }

    /**
     * @return array<string, mixed>
     */
    protected function sharedViewData(): array
    {
        return array_merge(parent::sharedViewData(), [
            'codeLabel' => $this->codeLabel(),
            'nameLabel' => $this->nameLabel(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(BaseModel $record): array
    {
        return array_merge($this->sharedViewData(), ['record' => $record]);
    }
}
