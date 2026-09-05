/**
 * 日付範囲ピッカー。
 *
 * 相対プリセット（今月・今四半期など）は「キー」を送るだけで、実際の期間は
 * サーバ側が受け取るたびに計算し直す。ここで持っている日付は表示用。
 */
import createPopup from './popup';

export default function dateRange(config = {}) {
    return {
        open: false,
        popup: null,
        presets: config.presets ?? [],
        preset: config.preset ?? 'none',
        from: config.from ?? '',
        to: config.to ?? '',
        submitOnChange: config.submitOnChange ?? false,
        noneLabel: config.noneLabel ?? '指定なし',

        init() {
            // パネルは開いている間だけ body 直下へ移し、画面内に収める
            // （狭い画面では下から出るシートにする）
            this.popup = createPopup({ anchor: this.$el, panel: this.$refs.panel, sheet: true });

            this.$watch('open', (value) => (value ? this.$nextTick(() => this.popup.show()) : this.popup.hide()));
        },

        destroy() {
            this.popup?.hide();
        },

        /** 外側のクリックで閉じる（body へ移したパネルの中は「外側」ではない） */
        closeFromOutside(event) {
            if (this.popup?.contains(event.target)) {
                return;
            }

            this.open = false;
        },

        /** ボタンに出す現在の期間 */
        get label() {
            if (this.from === '' && this.to === '') {
                return this.noneLabel;
            }

            const from = this.format(this.from);
            const to = this.format(this.to);

            if (from !== '' && to !== '') {
                return `${from} 〜 ${to}`;
            }

            return from !== '' ? `${from} 以降` : `${to} まで`;
        },

        get hasRange() {
            return this.from !== '' || this.to !== '';
        },

        format(value) {
            return (value ?? '').replaceAll('-', '/');
        },

        isSelected(value) {
            return this.preset === value;
        },

        selectPreset(item) {
            this.preset = item.value;
            this.from = item.from;
            this.to = item.to;
            this.open = false;

            this.changed();
        },

        /** 開始日・終了日を直接触ったらカスタム扱いにする */
        onCustomInput() {
            this.preset = this.from === '' && this.to === '' ? 'none' : 'custom';
        },

        clear() {
            this.preset = 'none';
            this.from = '';
            this.to = '';

            this.changed();
        },

        apply() {
            this.open = false;
            this.changed();
        },

        changed() {
            this.$dispatch('date-range-changed', { preset: this.preset, from: this.from, to: this.to });

            if (this.submitOnChange) {
                this.$nextTick(() => this.$el.closest('form')?.requestSubmit());
            }
        },
    };
}
