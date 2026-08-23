<?php

namespace App\Http\Controllers\Masters;

use App\Enums\EntityType;
use App\Enums\PartnerType;
use App\Http\Requests\Masters\PartnerRequest;
use App\Models\Partner;
use App\Support\DataTable\TableDefinition;
use App\Tables\PartnerTable;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class PartnerController extends MasterController
{
    protected function definition(): TableDefinition
    {
        return new PartnerTable;
    }

    protected function viewPath(): string
    {
        return 'masters.partners';
    }

    protected function modelClass(): string
    {
        return Partner::class;
    }

    protected function resourceLabel(): string
    {
        return '取引先';
    }

    public function create(): View
    {
        return view($this->viewPath().'.form', $this->formData(new Partner));
    }

    public function store(PartnerRequest $request): RedirectResponse
    {
        Partner::create($request->validated());

        return $this->redirectToIndex('取引先を登録しました。');
    }

    public function edit(int $id): View
    {
        $partner = Partner::query()->findOrFail($id);

        return view($this->viewPath().'.form', $this->formData($partner));
    }

    public function update(PartnerRequest $request, int $id): RedirectResponse
    {
        Partner::query()->findOrFail($id)->update($request->validated());

        return $this->redirectToIndex('取引先を更新しました。');
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(Partner $partner): array
    {
        return array_merge($this->sharedViewData(), [
            'partner' => $partner,
            'partnerTypeOptions' => PartnerType::options(),
            'entityTypeOptions' => EntityType::options(),
        ]);
    }
}
