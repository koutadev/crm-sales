<?php

namespace App\Http\Controllers\Masters;

use App\Http\Requests\Masters\TaxRateRequest;
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
     * @return array<string, mixed>
     */
    private function formData(TaxRate $taxRate): array
    {
        return array_merge($this->sharedViewData(), ['taxRate' => $taxRate]);
    }
}
