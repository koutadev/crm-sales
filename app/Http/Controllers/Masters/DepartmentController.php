<?php

namespace App\Http\Controllers\Masters;

use App\Models\Department;

class DepartmentController extends SimpleMasterController
{
    protected function modelClass(): string
    {
        return Department::class;
    }

    protected function resourceKey(): string
    {
        return 'departments';
    }

    protected function resourceLabel(): string
    {
        return '部署';
    }

    protected function codeLabel(): string
    {
        return '部署コード';
    }

    protected function nameLabel(): string
    {
        return '部署名';
    }
}
