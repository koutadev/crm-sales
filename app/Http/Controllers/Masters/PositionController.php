<?php

namespace App\Http\Controllers\Masters;

use App\Models\Position;

class PositionController extends SimpleMasterController
{
    protected function modelClass(): string
    {
        return Position::class;
    }

    protected function resourceKey(): string
    {
        return 'positions';
    }

    protected function resourceLabel(): string
    {
        return '役職';
    }

    protected function codeLabel(): string
    {
        return '役職コード';
    }

    protected function nameLabel(): string
    {
        return '役職名';
    }
}
