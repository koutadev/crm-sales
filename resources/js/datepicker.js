/**
 * カレンダー（日付選択）。
 *
 * ブラウザ標準の date input に頼らず、見た目と操作を自前で持つ。
 * 依存は Alpine だけ。
 */
const pad = (value) => String(value).padStart(2, '0');

const toKey = (year, month, day) => `${year}-${pad(month)}-${pad(day)}`;

export default function datepicker(config = {}) {
    return {
        value: config.value ?? '',
        display: '',
        open: false,
        viewYear: 0,
        viewMonth: 1,
        focused: '',
        holidays: config.holidays ?? {},
        min: config.min ?? '',
        max: config.max ?? '',
        weekStart: config.weekStart ?? 1,

        init() {
            this.syncFromValue();

            // 外から value を変えられた場合（日付範囲ピッカーのプリセット選択など）
            this.$watch('value', () => this.syncFromValue());
        },

        /** value（YYYY-MM-DD）から表示と表示中の月を組み立てる */
        syncFromValue() {
            const parsed = this.parse(this.value);
            const base = parsed ?? this.todayParts();

            this.display = parsed ? this.formatDisplay(parsed) : '';
            this.viewYear = base.year;
            this.viewMonth = base.month;
            this.focused = parsed ? this.value : toKey(base.year, base.month, base.day);
        },

        todayParts() {
            const now = new Date();

            return { year: now.getFullYear(), month: now.getMonth() + 1, day: now.getDate() };
        },

        get todayKey() {
            const { year, month, day } = this.todayParts();

            return toKey(year, month, day);
        },

        get monthLabel() {
            return `${this.viewYear}年${this.viewMonth}月`;
        },

        /** 選べる年（前後 10 年） */
        get years() {
            const base = this.viewYear;

            return Array.from({ length: 21 }, (unused, index) => base - 10 + index);
        },

        get months() {
            return Array.from({ length: 12 }, (unused, index) => index + 1);
        },

        /** 曜日の見出し（週の開始曜日に合わせて回す） */
        get weekdays() {
            const names = ['日', '月', '火', '水', '木', '金', '土'];

            return Array.from({ length: 7 }, (unused, index) => {
                const dow = (this.weekStart + index) % 7;

                return { dow, label: names[dow] };
            });
        },

        /** 月のマス目（6 週ぶん） */
        get weeks() {
            const first = new Date(this.viewYear, this.viewMonth - 1, 1);
            const offset = (first.getDay() - this.weekStart + 7) % 7;
            const start = new Date(this.viewYear, this.viewMonth - 1, 1 - offset);
            const weeks = [];

            for (let week = 0; week < 6; week++) {
                const days = [];

                for (let day = 0; day < 7; day++) {
                    const date = new Date(start.getFullYear(), start.getMonth(), start.getDate() + week * 7 + day);
                    const key = toKey(date.getFullYear(), date.getMonth() + 1, date.getDate());

                    days.push({
                        key,
                        day: date.getDate(),
                        dow: date.getDay(),
                        inMonth: date.getMonth() + 1 === this.viewMonth,
                        disabled: this.isDisabled(key),
                        holiday: this.holidays[key] ?? null,
                        label: `${date.getFullYear()}年${date.getMonth() + 1}月${date.getDate()}日`,
                    });
                }

                weeks.push(days);
            }

            return weeks;
        },

        isDisabled(key) {
            return (this.min !== '' && key < this.min) || (this.max !== '' && key > this.max);
        },

        isSelected(key) {
            return this.value === key;
        },

        isToday(key) {
            return this.todayKey === key;
        },

        /** 土曜・日曜・祝日の色分け */
        dayTone(day) {
            if (day.holiday !== null || day.dow === 0) {
                return 'sunday';
            }

            return day.dow === 6 ? 'saturday' : 'weekday';
        },

        toggle() {
            this.open = !this.open;

            if (this.open) {
                this.$nextTick(() => this.focusDay());
            }
        },

        close() {
            this.open = false;
        },

        pick(key) {
            if (this.isDisabled(key)) {
                return;
            }

            this.value = key;
            this.focused = key;
            this.open = false;

            this.$dispatch('datepicker-changed', { value: key });
        },

        clear() {
            this.value = '';
            this.display = '';
            this.open = false;

            this.$dispatch('datepicker-changed', { value: '' });
        },

        selectToday() {
            this.showMonthOf(this.todayKey);
            this.pick(this.todayKey);
        },

        showMonthOf(key) {
            const parsed = this.parse(key);

            if (parsed) {
                this.viewYear = parsed.year;
                this.viewMonth = parsed.month;
            }
        },

        shiftMonth(step) {
            const date = new Date(this.viewYear, this.viewMonth - 1 + step, 1);

            this.viewYear = date.getFullYear();
            this.viewMonth = date.getMonth() + 1;
        },

        /** 矢印キーでの移動（月をまたいだら表示も追従する） */
        moveFocus(days) {
            const parsed = this.parse(this.focused) ?? this.todayParts();
            const date = new Date(parsed.year, parsed.month - 1, parsed.day + days);
            const key = toKey(date.getFullYear(), date.getMonth() + 1, date.getDate());

            this.focused = key;
            this.viewYear = date.getFullYear();
            this.viewMonth = date.getMonth() + 1;

            this.$nextTick(() => this.focusDay());
        },

        focusDay() {
            this.$refs.grid?.querySelector(`[data-date="${this.focused}"]`)?.focus();
        },

        /** 入力欄に直接打たれた日付を読み取る（2026/08/24・2026-08-24・20260824） */
        parseDisplay() {
            const raw = (this.display ?? '').trim();

            if (raw === '') {
                this.clear();

                return;
            }

            const digits = raw.replace(/[^0-9]/g, '');

            if (digits.length !== 8) {
                // 読み取れなければ元の値に戻す
                this.syncFromValue();

                return;
            }

            const key = `${digits.slice(0, 4)}-${digits.slice(4, 6)}-${digits.slice(6, 8)}`;

            if (this.parse(key) === null || this.isDisabled(key)) {
                this.syncFromValue();

                return;
            }

            this.value = key;
            this.syncFromValue();
            this.$dispatch('datepicker-changed', { value: key });
        },

        parse(key) {
            if (typeof key !== 'string' || !/^\d{4}-\d{2}-\d{2}$/.test(key)) {
                return null;
            }

            const [year, month, day] = key.split('-').map(Number);
            const date = new Date(year, month - 1, day);

            if (date.getFullYear() !== year || date.getMonth() + 1 !== month || date.getDate() !== day) {
                return null;
            }

            return { year, month, day };
        },

        formatDisplay({ year, month, day }) {
            return `${year}/${pad(month)}/${pad(day)}`;
        },
    };
}
