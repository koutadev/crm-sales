/**
 * 背面のスクロールを止める（モーダル・モバイルのドロワーで共通）。
 *
 * 複数のものが同時に開くことがある（ドロワーの上でモーダルを開くなど）ので、
 * 「いま止めている人」を数え、最後の 1 つが閉じたときだけ元に戻す。
 *
 *   lockScroll('modal:edit-employee');
 *   unlockScroll('modal:edit-employee');
 */
const CLASS = 'overflow-y-hidden';

const owners = new Set();

export function lockScroll(owner) {
    owners.add(owner);
    document.body.classList.add(CLASS);
}

export function unlockScroll(owner) {
    owners.delete(owner);

    if (owners.size === 0) {
        document.body.classList.remove(CLASS);
    }
}
