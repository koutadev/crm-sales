<?php

namespace App\Support\Dashboard;

use App\Support\Theme\Theme;

/**
 * ダッシュボードのグラフ 1 つぶん。
 *
 * コントローラで「ラベルと値」だけを組み立て、Chart.js の設定への変換はここに閉じ込める。
 * 各システムでグラフを差し替えるときも、必要なのはラベルと値の用意だけ。
 *
 *   Chart::doughnut('取引先区分', ['得意先' => 12, '仕入先' => 8]);
 *   Chart::bar('分類別 商品件数', ['IT機器' => 10, '消耗品' => 4], '件数');
 *
 * 色はテーマ（config/theme.php）から取るため、配色を変えるとグラフも追従する。
 */
class Chart
{
    /**
     * @param  'doughnut'|'bar'|'line'  $type
     * @param  array<string, int|float>  $data  [ラベル => 値]
     */
    private function __construct(
        public readonly string $id,
        public readonly string $type,
        public readonly string $title,
        public readonly array $data,
        public readonly string $datasetLabel = '',
        /** @var list<string> 目盛りとは別に、ツールチップへ出す表示(空なら目盛りのまま) */
        public readonly array $tooltipLabels = [],
        /** @var array{label: string, data: array<string, int|float>}|null 重ねて見せる比較系列(目標など) */
        public readonly ?array $comparison = null,
    ) {}

    /**
     * 目標などの比較系列を重ねる(破線で描画される)。
     *
     * @param  array<string, int|float>  $data  [ラベル => 値]。本系列と同じ並び
     */
    public function withComparison(string $label, array $data): self
    {
        return new self(
            $this->id,
            $this->type,
            $this->title,
            $this->data,
            $this->datasetLabel,
            $this->tooltipLabels,
            ['label' => $label, 'data' => $data],
        );
    }

    /**
     * 目盛りは短く、ツールチップでは正式名称を出す。
     *
     * 「担当者別」のように名前が長くて軸が詰まる場合に使う。
     *
     *   Chart::bar('sales', '担当者別', ['山田' => 100])->withTooltipLabels(['山田 太郎']);
     *
     * @param  list<string>  $labels  data と同じ並び
     */
    public function withTooltipLabels(array $labels): self
    {
        return new self($this->id, $this->type, $this->title, $this->data, $this->datasetLabel, $labels, $this->comparison);
    }

    /**
     * 読み上げ・キーボード向けの説明(グラフの中身を文章で持たせる)。
     */
    public function summary(): string
    {
        if ($this->isEmpty()) {
            return $this->title.'：表示できるデータがありません。';
        }

        $comparison = $this->comparison;

        $parts = [];
        $labels = array_keys($this->data);
        $values = array_values($this->data);

        foreach ($labels as $index => $label) {
            $name = $this->tooltipLabels[$index] ?? (string) $label;
            $part = $name.' '.number_format($values[$index]);

            if ($comparison !== null) {
                $part .= '（'.$comparison['label'].' '.number_format($comparison['data'][$label] ?? 0).'）';
            }

            $parts[] = $part;
        }

        return $this->title.'：'.implode('、', $parts);
    }

    /**
     * @param  array<string, int|float>  $data
     */
    public static function doughnut(string $id, string $title, array $data): self
    {
        return new self($id, 'doughnut', $title, $data);
    }

    /**
     * @param  array<string, int|float>  $data
     */
    public static function bar(string $id, string $title, array $data, string $datasetLabel = ''): self
    {
        return new self($id, 'bar', $title, $data, $datasetLabel);
    }

    /**
     * 推移を見せる折れ線グラフ。
     *
     * @param  array<string, int|float>  $data
     */
    public static function line(string $id, string $title, array $data, string $datasetLabel = ''): self
    {
        return new self($id, 'line', $title, $data, $datasetLabel);
    }

    public function isEmpty(): bool
    {
        return $this->data === [] || array_sum($this->data) === 0;
    }

    /**
     * Chart.js にそのまま渡せる設定。
     *
     * @return array<string, mixed>
     */
    public function toChartJs(): array
    {
        $labels = array_keys($this->data);
        $values = array_values($this->data);
        // 比較系列があるときは色を 2 本ぶん確保する
        $colors = $this->colorsFor(max(2, count($values)));

        $dataset = [
            'label' => $this->datasetLabel !== '' ? $this->datasetLabel : $this->title,
            'data' => $values,
            'backgroundColor' => $this->type === 'doughnut' ? $colors : $colors[0],
            'borderWidth' => 0,
            'borderRadius' => $this->type === 'bar' ? 4 : 0,
        ];

        if ($this->type === 'line') {
            $dataset['borderColor'] = $colors[0];
            $dataset['borderWidth'] = 2;
            $dataset['tension'] = 0.3;
            $dataset['fill'] = false;
            $dataset['pointRadius'] = 3;
        }

        $datasets = [$dataset];

        if ($this->comparison !== null) {
            // 目標などの比較系列。実績と見分けられるよう破線にする
            $datasets[] = [
                'label' => $this->comparison['label'],
                'type' => 'line',
                'data' => array_map(
                    fn (string $label): int|float => $this->comparison['data'][$label] ?? 0,
                    $labels,
                ),
                'borderColor' => $colors[1] ?? $colors[0],
                'backgroundColor' => 'transparent',
                'borderWidth' => 2,
                'borderDash' => [6, 4],
                'pointRadius' => 0,
                'fill' => false,
                'tension' => 0,
            ];
        }

        $data = [
            'labels' => $labels,
            'datasets' => $datasets,
        ];

        if ($this->tooltipLabels !== []) {
            // 実際の差し替えは resources/js/charts.js が行う
            $data['tooltipLabels'] = $this->tooltipLabels;
        }

        return [
            'type' => $this->type,
            'data' => $data,
            'options' => $this->options(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function options(): array
    {
        $common = [
            'responsive' => true,
            'maintainAspectRatio' => false,
            'plugins' => [
                'legend' => [
                    // 比較系列(目標)があるときは、どちらの線か分かるよう凡例を出す
                    'display' => $this->type === 'doughnut' || $this->comparison !== null,
                    'position' => 'bottom',
                ],
            ],
        ];

        if ($this->type === 'bar' || $this->type === 'line') {
            $common['scales'] = [
                // 件数・金額とも整数で扱うので目盛りは整数のみ
                'y' => ['beginAtZero' => true, 'ticks' => ['precision' => 0]],
            ];
        }

        return $common;
    }

    /**
     * @return list<string>
     */
    private function colorsFor(int $count): array
    {
        $palette = Theme::chartPalette();

        if ($palette === []) {
            return array_fill(0, max($count, 1), '#64748b');
        }

        $colors = [];

        for ($i = 0; $i < max($count, 1); $i++) {
            $colors[] = $palette[$i % count($palette)];
        }

        return $colors;
    }
}
