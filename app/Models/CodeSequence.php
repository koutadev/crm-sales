<?php

namespace App\Models;

use App\Support\Code\CodeGenerator;
use Illuminate\Database\Eloquent\Model;

/**
 * 業務コードの採番カウンタ。
 *
 * 1 レコード = 1 採番系列(通常はテーブル名)。
 * 更新は必ず {@see CodeGenerator} 経由で行い、行ロックで採番の重複を防ぐ。
 *
 * @property string $key
 * @property int $next_number
 */
class CodeSequence extends Model
{
    protected $table = 'code_sequences';

    protected $primaryKey = 'key';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = ['key', 'next_number'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'next_number' => 'integer',
        ];
    }
}
