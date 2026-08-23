<?php

namespace App\Models\Concerns;

use App\Support\Code\CodeGenerator;

/**
 * 業務コード(code カラム)を自動採番する。
 *
 * 使う側のモデルで codePrefix() を実装する。
 *
 *   class Employee extends BaseModel
 *   {
 *       use HasSequentialCode;
 *
 *       public static function codePrefix(): string
 *       {
 *           return 'EMP';   // → EMP-0001, EMP-0002, ...
 *       }
 *   }
 *
 * code に値が入っている状態で保存した場合は採番せずその値を使う
 * (データ移行や外部システムからの取り込みに対応するため)。
 *
 * @property string $code
 */
trait HasSequentialCode
{
    /** 連番のゼロ埋め桁数 */
    protected static int $codePadding = 4;

    /**
     * コードのプレフィックス(例: EMP)。
     */
    abstract public static function codePrefix(): string;

    public static function bootHasSequentialCode(): void
    {
        static::creating(function (self $model): void {
            if (filled($model->getAttribute('code'))) {
                return;
            }

            $model->setAttribute('code', static::generateCode());
        });
    }

    /**
     * 次のコードを採番する。
     */
    public static function generateCode(): string
    {
        return app(CodeGenerator::class)->next(
            key: (new static)->getTable(),
            prefix: static::codePrefix(),
            padding: static::$codePadding,
        );
    }

    /**
     * 採番系列のキー(= テーブル名)。
     */
    public static function codeSequenceKey(): string
    {
        return (new static)->getTable();
    }
}
