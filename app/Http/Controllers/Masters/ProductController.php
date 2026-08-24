<?php

namespace App\Http\Controllers\Masters;

use App\Http\Requests\Masters\ProductRequest;
use App\Models\BaseModel;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\TaxRate;
use App\Support\DataTable\TableDefinition;
use App\Tables\ProductTable;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ProductController extends MasterController
{
    protected function definition(): TableDefinition
    {
        return new ProductTable;
    }

    protected function viewPath(): string
    {
        return 'masters.products';
    }

    protected function modelClass(): string
    {
        return Product::class;
    }

    protected function resourceLabel(): string
    {
        return '商品';
    }

    public function create(): View
    {
        return view($this->viewPath().'.form', $this->formData(new Product));
    }

    public function store(ProductRequest $request): RedirectResponse
    {
        Product::create($request->validated());

        return $this->redirectToIndex('商品を登録しました。');
    }

    public function edit(int $id): View
    {
        $product = Product::query()->findOrFail($id);

        return view($this->viewPath().'.form', $this->formData($product));
    }

    public function update(ProductRequest $request, int $id): RedirectResponse
    {
        Product::query()->findOrFail($id)->update($request->validated());

        return $this->redirectToIndex('商品を更新しました。');
    }

    /**
     * @return array<string, string|null>
     */
    protected function detailRows(BaseModel $record): array
    {
        /** @var Product $record */
        return [
            '商品コード' => $record->code,
            '商品名' => $record->name,
            '分類' => $record->category?->name,
            '標準単価(税込)' => number_format($record->unit_price),
            '税率' => $record->taxRate?->label(),
            '単位' => $record->unit,
            '状態' => $record->activeLabel(),
            '最終更新' => $record->updated_at?->format('Y/m/d H:i'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function formData(BaseModel $product): array
    {
        /** @var Product $product */
        // 新規登録時は既定の標準税率を初期選択にしておく(未選択で保存しても同じ税率が入る)
        if (! $product->exists && $product->tax_rate_id === null) {
            $product->tax_rate_id = TaxRate::standard()?->id;
        }

        return array_merge($this->sharedViewData(), [
            'product' => $product,
            'categoryOptions' => ProductCategory::query()->active()->orderBy('code')->pluck('name', 'id')->all(),
            'taxRateOptions' => TaxRate::options(activeOnly: true),
            'standardTaxRate' => TaxRate::standard(),
        ]);
    }
}
