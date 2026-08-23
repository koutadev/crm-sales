<?php

namespace App\Support\DataTable;

use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * 一覧の CSV 出力。
 *
 * Excel で文字化けしないよう UTF-8 BOM 付き・CRLF 改行で出力する。
 * 件数が多くてもメモリを使い切らないよう、1 行ずつストリーミングする。
 */
class CsvExporter
{
    public function __construct(
        private readonly TableDefinition $definition,
        private readonly TableBuilder $builder,
    ) {}

    public function download(): StreamedResponse
    {
        $fileName = $this->definition->exportFileName().'_'.now()->format('Ymd_His').'.csv';

        return response()->stream(function (): void {
            $handle = fopen('php://output', 'w');

            if ($handle === false) {
                return;
            }

            // Excel が UTF-8 と判定できるように BOM を先頭に出す
            fwrite($handle, "\xEF\xBB\xBF");

            $this->writeRow($handle, array_map(
                static fn (Column $column): string => $column->label,
                $this->definition->columns(),
            ));

            foreach ($this->builder->lazy() as $model) {
                $this->writeRow($handle, $this->definition->toCsvRow($model));
            }

            fclose($handle);
        }, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$fileName.'"',
            'Cache-Control' => 'no-store, no-cache',
        ]);
    }

    /**
     * @param  resource  $handle
     * @param  array<int, string|int|float|null>  $row
     */
    private function writeRow($handle, array $row): void
    {
        $values = array_map(static fn (string|int|float|null $value): string => (string) $value, $row);

        // Excel の仕様に合わせて改行は CRLF にする
        fwrite($handle, $this->toCsvLine($values)."\r\n");
    }

    /**
     * @param  array<int, string>  $values
     */
    private function toCsvLine(array $values): string
    {
        return implode(',', array_map(
            fn (string $value): string => '"'.str_replace('"', '""', $this->neutralizeFormula($value)).'"',
            $values,
        ));
    }

    /**
     * CSV インジェクション対策。
     *
     * "=cmd|..." のような値をそのまま出すと、Excel で開いたときに数式として実行され得る。
     * 先頭が数式扱いされる文字の場合はシングルクォートを付けて無効化する。
     */
    private function neutralizeFormula(string $value): string
    {
        if ($value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
            return "'".$value;
        }

        return $value;
    }
}
