<?php

namespace App\Http\Controllers\Masters;

use App\Models\ProductCategory;

class ProductCategoryController extends SimpleMasterController
{
    protected function modelClass(): string
    {
        return ProductCategory::class;
    }

    protected function resourceKey(): string
    {
        return 'product-categories';
    }

    protected function resourceLabel(): string
    {
        return '商品分類';
    }

    protected function codeLabel(): string
    {
        return '分類コード';
    }

    protected function nameLabel(): string
    {
        return '分類名';
    }
}
