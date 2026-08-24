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
    ) {}

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
        $colors = $this->colorsFor(count($values));

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

        return [
            'type' => $this->type,
            'data' => [
                'labels' => $labels,
                'datasets' => [$dataset],
            ],
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
                    'display' => $this->type === 'doughnut',
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
