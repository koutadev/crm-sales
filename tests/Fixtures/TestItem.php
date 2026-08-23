<?php

namespace Tests\Fixtures;

use App\Models\BaseModel;

/**
 * 共通仕様(BaseModel)を検証するためのテスト専用モデル。
 *
 * 対応するテーブルは各テストの setUp() で作成する。
 *
 * @property string $name
 */
class TestItem extends BaseModel
{
    protected $table = 'test_items';

    protected $fillable = ['name'];
}
