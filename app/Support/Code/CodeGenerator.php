<?php

namespace App\Support\Code;

use App\Models\CodeSequence;
use Illuminate\Support\Facades\DB;

/**
 * 業務コードの自動採番。
 *
 *   EMP-0001 / PTR-0001 / PRD-0001 …（プレフィックス + "-" + ゼロ埋め連番）
 *
 * 採番カウンタは code_sequences テーブルで系列ごとに管理し、
 * 採番時に行ロック(SELECT ... FOR UPDATE)を取ることで、同時登録でも番号が重複しない。
 *
 * 欠番について: 採番後にトランザクションが失敗すると番号は欠番になる。
 * 業務コードは連続性より一意性が重要なため、これを許容する設計にしている。
 */
class CodeGenerator
{
    /**
     * 次の業務コードを採番する。
     *
     * @param  string  $key  採番系列(通常はテーブル名)
     * @param  string  $prefix  コードのプレフィックス(例: EMP)
     * @param  int  $padding  連番のゼロ埋め桁数
     * @param  int  $startAt  系列が未作成のときの開始番号
     */
    public function next(string $key, string $prefix, int $padding = 4, int $startAt = 1): string
    {
        return DB::transaction(function () use ($key, $prefix, $padding, $startAt): string {
            // 同時実行で重複挿入されないよう ON CONFLICT DO NOTHING で作成する
            CodeSequence::query()->insertOrIgnore([
                'key' => $key,
                'next_number' => $startAt,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            /** @var CodeSequence $sequence */
            $sequence = CodeSequence::query()->lockForUpdate()->findOrFail($key);

            $number = $sequence->next_number;

            $sequence->next_number = $number + 1;
            $sequence->save();

            return $this->format($prefix, $number, $padding);
        });
    }

    /**
     * コード文字列を組み立てる(採番はしない)。
     */
    public function format(string $prefix, int $number, int $padding = 4): string
    {
        return $prefix.'-'.str_pad((string) $number, $padding, '0', STR_PAD_LEFT);
    }

    /**
     * 既存データを取り込んだ後などに、採番カウンタを指定番号の次に合わせる。
     */
    public function syncTo(string $key, int $lastUsedNumber): void
    {
        CodeSequence::query()->updateOrCreate(
            ['key' => $key],
            ['next_number' => $lastUsedNumber + 1],
        );
    }
}
