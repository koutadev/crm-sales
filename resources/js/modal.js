/**
 * モーダル。
 *
 * 開いているあいだはフォーカスをモーダル内に閉じ込め（フォーカストラップ）、
 * 閉じたら開く前の要素へフォーカスを戻す。背景のスクロールも止める。
 *
 * 開閉は window のイベントで行う。開く側は次の 3 通りが使える。
 *
 *   <button data-open-modal="edit-employee">編集</button>  // どこからでも（推奨）
 *   $dispatch('open-modal', 'edit-employee')                // Alpine のスコープ内から
 *   window.openModal('edit-employee')                       // 素の JS から
 *
 * $dispatch は Alpine のマジックなので、x-data の外にあるボタンでは何も起きない
 * （レイアウトにルートの x-data が無いと、押しても沈黙して壊れる）。
 * data-open-modal は document でクリックを拾って window イベントに変換するため、
 * Alpine のスコープに関係なく動く。
 */
/** 名前つきモーダルを開く（素の JS から呼べる） */
export function openModal(name) {
    window.dispatchEvent(new CustomEvent('open-modal', { detail: name }));
}

/** 名前つきモーダルを閉じる */
export function closeModal(name) {
    window.dispatchEvent(new CustomEvent('close-modal', { detail: name }));
}

/**
 * data-open-modal / data-close-modal のクリックを拾って、window イベントに変換する。
 *
 * Alpine の起動前・スコープ外でも効くよう、document へ 1 つだけ登録する。
 */
export function registerModalTriggers() {
    window.openModal = openModal;
    window.closeModal = closeModal;

    document.addEventListener('click', (event) => {
        const opener = event.target.closest('[data-open-modal]');

        if (opener) {
            event.preventDefault();
            openModal(opener.dataset.openModal);

            return;
        }

        const closer = event.target.closest('[data-close-modal]');

        if (closer) {
            event.preventDefault();
            closeModal(closer.dataset.closeModal);
        }
    });
}

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
