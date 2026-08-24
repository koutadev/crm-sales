/**
 * トースト通知の入れ物。
 *
 * 画面のどこからでも出せるように Alpine のストアにしてある。
 *
 *   Alpine.store('toast').push({ type: 'success', message: '保存しました' })
 *   $dispatch('toast', { type: 'error', message: '保存に失敗しました' })
 */
const DEFAULT_TIMEOUT = 4000;

export default function registerToastStore(Alpine) {
    Alpine.store('toast', {
        items: [],
        nextId: 1,

        push({ type = 'info', message = '', timeout = DEFAULT_TIMEOUT } = {}) {
            if (message === '') {
                return;
            }

            const id = this.nextId++;

            this.items.push({ id, type, message });

            if (timeout > 0) {
                setTimeout(() => this.remove(id), timeout);
            }
        },

        remove(id) {
            this.items = this.items.filter((item) => item.id !== id);
        },
    });

    // どの階層からでも $dispatch('toast', {...}) で出せるようにする
    window.addEventListener('toast', (event) => {
        Alpine.store('toast').push(event.detail ?? {});
    });

    // Alpine のスコープが無い場所（素の onclick など）からも呼べるようにしておく
    window.toast = (message, type = 'info') => {
        Alpine.store('toast').push({ message, type });
    };
}
