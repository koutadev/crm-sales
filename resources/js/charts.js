import Chart from 'chart.js/auto';

/**
 * ダッシュボードのグラフ描画。
 *
 * Blade 側は <canvas data-chart="{Chart.js の設定 JSON}"> を置くだけでよく、
 * グラフごとに JavaScript を書く必要はない。
 * 設定は PHP 側（App\Support\Dashboard\Chart）で組み立てている。
 */

/** ダークモードかどうかで軸ラベルなどの文字色を切り替える */
const isDarkMode = () => window.matchMedia('(prefers-color-scheme: dark)').matches;

const applyDefaults = () => {
    Chart.defaults.font.family =
        getComputedStyle(document.body).fontFamily || 'system-ui, sans-serif';
    Chart.defaults.color = isDarkMode() ? '#9ca3af' : '#4b5563';
    Chart.defaults.borderColor = isDarkMode()
        ? 'rgba(255,255,255,0.08)'
        : 'rgba(0,0,0,0.06)';
};

/**
 * 目盛りは短く、ツールチップでは正式名称を出す。
 *
 * PHP 側から data.tooltipLabels（目盛りと同じ並び）が渡されたときだけ差し替える。
 */
const withTooltipLabels = (config) => {
    const labels = config.data?.tooltipLabels;

    if (! Array.isArray(labels) || labels.length === 0) {
        return config;
    }

    config.options = config.options ?? {};
    config.options.plugins = config.options.plugins ?? {};
    config.options.plugins.tooltip = config.options.plugins.tooltip ?? {};
    config.options.plugins.tooltip.callbacks = {
        ...(config.options.plugins.tooltip.callbacks ?? {}),
        title: (items) => labels[items[0]?.dataIndex] ?? items[0]?.label ?? '',
    };

    return config;
};

const renderCharts = () => {
    applyDefaults();

    document.querySelectorAll('canvas[data-chart]').forEach((canvas) => {
        if (canvas.dataset.chartRendered === '1') {
            return;
        }

        try {
            new Chart(canvas, withTooltipLabels(JSON.parse(canvas.dataset.chart)));
            canvas.dataset.chartRendered = '1';
        } catch (error) {
            console.error('グラフの描画に失敗しました', error);
        }
    });
};

document.addEventListener('DOMContentLoaded', renderCharts);
