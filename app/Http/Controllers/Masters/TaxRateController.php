<?php

namespace App\Http\Controllers\Masters;

use App\Http\Requests\Masters\TaxRateRequest;
use App\Models\BaseModel;
use App\Models\TaxRate;
use App\Support\DataTable\TableDefinition;
use App\Tables\TaxRateTable;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class TaxRateController extends MasterController
{
    protected function definition(): TableDefinition
    {
        return new TaxRateTable;
    }

    protected function viewPath(): string
    {
        return 'masters.tax-rates';
    }

    protected function modelClass(): string
    {
        return TaxRate::class;
    }

    protected function resourceLabel(): string
    {
        return '税率';
    }

    public function create(): View
    {
        return view($this->viewPath().'.form', $this->formData(new TaxRate));
    }

    public function store(TaxRateRequest $request): RedirectResponse
    {
        TaxRate::create($request->validated());

        return $this->redirectToIndex('税率を登録しました。');
    }

    public function edit(int $id): View
    {
        $taxRate = TaxRate::query()->findOrFail($id);

        return view($this->viewPath().'.form', $this->formData($taxRate));
    }

    public function update(TaxRateRequest $request, int $id): RedirectResponse
    {
        TaxRate::query()->findOrFail($id)->update($request->validated());

        return $this->redirectToIndex('税率を更新しました。');
    }

    /**
     * @return array<string, string|null>
     */
    protected function detailRows(BaseModel $record): array
    {
        /** @var TaxRate $record */
        return [
            '税率名' => $record->name,
            '税率' => $record->rate_percent.'%',
            '適用開始日' => $record->effective_from->format('Y/m/d'),
            '状態' => $record->activeLabel(),
            '登録日時' => $record->created_at?->format('Y/m/d H:i'),
            '最終更新' => $record->updated_at?->format('Y/m/d H:i'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function formData(BaseModel $taxRate): array
    {
        /** @var TaxRate $taxRate */
        return array_merge($this->sharedViewData(), ['taxRate' => $taxRate]);
    }
}
