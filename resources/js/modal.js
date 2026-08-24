/**
 * モーダル。
 *
 * 開いているあいだはフォーカスをモーダル内に閉じ込め（フォーカストラップ）、
 * 閉じたら開く前の要素へフォーカスを戻す。背景のスクロールも止める。
 *
 *   $dispatch('open-modal', 'edit-employee')   // 開く
 *   $dispatch('close-modal', 'edit-employee')  // 閉じる
 *   $dispatch('close')                         // モーダル内から閉じる
 */
const FOCUSABLE =
    'a[href], button:not([disabled]), textarea:not([disabled]), select:not([disabled]), ' +
    "input:not([type='hidden']):not([disabled]), [tabindex]:not([tabindex='-1'])";

export default function modal({ name = null, show = false, closable = true } = {}) {
    return {
        name,
        show,
        closable,
        previouslyFocused: null,

        init() {
            // 初期表示（バリデーションエラーで開き直す場合など）
            if (this.show) {
                this.$nextTick(() => this.afterOpen());
            }

            this.$watch('show', (value) => (value ? this.afterOpen() : this.afterClose()));
        },

        matches(detail) {
            return this.name !== null && detail === this.name;
        },

        open() {
            this.previouslyFocused = document.activeElement;
            this.show = true;
        },

        close(force = false) {
            if (!force && !this.closable) {
                return;
            }

            this.show = false;
        },

        afterOpen() {
            document.body.classList.add('overflow-y-hidden');

            this.$nextTick(() => {
                const target = this.focusables()[0] ?? this.$refs.panel;

                target?.focus();
            });
        },

        afterClose() {
            document.body.classList.remove('overflow-y-hidden');

            this.previouslyFocused?.focus?.();
            this.previouslyFocused = null;
        },

        focusables() {
            return Array.from(this.$refs.panel?.querySelectorAll(FOCUSABLE) ?? []);
        },

        /** Tab をモーダル内で循環させる */
        trap(event) {
            const focusables = this.focusables();

            if (focusables.length === 0) {
                event.preventDefault();

                return;
            }

            const first = focusables[0];
            const last = focusables[focusables.length - 1];

            if (event.shiftKey && document.activeElement === first) {
                event.preventDefault();
                last.focus();
            } else if (!event.shiftKey && document.activeElement === last) {
                event.preventDefault();
                first.focus();
            }
        },
    };
}
