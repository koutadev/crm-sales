<?php

namespace App\Http\Controllers\Masters;

use App\Enums\OrganizationType;
use App\Http\Requests\Masters\OrganizationRequest;
use App\Models\BaseModel;
use App\Models\Organization;
use App\Support\DataTable\TableDefinition;
use App\Tables\OrganizationTable;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class OrganizationController extends MasterController
{
    protected function definition(): TableDefinition
    {
        return new OrganizationTable;
    }

    protected function viewPath(): string
    {
        return 'masters.organizations';
    }

    protected function modelClass(): string
    {
        return Organization::class;
    }

    protected function resourceLabel(): string
    {
        return '組織';
    }

    public function create(): View
    {
        return view($this->viewPath().'.form', $this->formData(new Organization));
    }

    public function store(OrganizationRequest $request): RedirectResponse
    {
        Organization::create($request->validated());

        return $this->redirectToIndex('組織を登録しました。');
    }

    public function edit(int $id): View
    {
        $organization = Organization::query()->findOrFail($id);

        return view($this->viewPath().'.form', $this->formData($organization));
    }

    public function update(OrganizationRequest $request, int $id): RedirectResponse
    {
        Organization::query()->findOrFail($id)->update($request->validated());

        return $this->redirectToIndex('組織を更新しました。');
    }

    /**
     * @return array<string, string|null>
     */
    protected function detailRows(BaseModel $record): array
    {
        /** @var Organization $record */
        return [
            '組織コード' => $record->code,
            '種別' => $record->type->label(),
            '組織名' => $record->name,
            '上位組織' => $record->parent?->name,
            '階層' => $record->path(),
            '所属社員' => number_format($record->employees()->count()).' 名',
            '状態' => $record->activeLabel(),
            '登録日時' => $record->created_at?->format('Y/m/d H:i'),
            '最終更新' => $record->updated_at?->format('Y/m/d H:i'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function formData(BaseModel $organization): array
    {
        /** @var Organization $organization */
        return array_merge($this->sharedViewData(), [
            'organization' => $organization,
            'typeOptions' => OrganizationType::options(),
            // 上位に選べるのは地域とエリアだけ(店舗の下は作らない)
            'parentOptions' => Organization::query()
                ->active()
                ->whereIn('type', [OrganizationType::Region->value, OrganizationType::Area->value])
                ->with('parent:id,name')
                ->orderBy('type')
                ->orderBy('code')
                ->get()
                ->mapWithKeys(static fn (Organization $node): array => [$node->id => $node->path()])
                ->all(),
        ]);
    }
}
