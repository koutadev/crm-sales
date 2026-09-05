/**
 * ポップアップ（カレンダー・候補リスト・期間ピッカー）の置き場所。
 *
 * 素直に position: absolute で出すと、モバイルで次の 3 つが起きる。
 *   - 画面の下や横にはみ出して、中の「適用」ボタンに届かない
 *   - overflow: hidden の器（カード・モーダルのパネル）に切られる
 *   - z-index が追従ヘッダーより低くて沈む
 *
 * そこで開いている間だけ body 直下へ移し、画面の座標で置き直す。
 *   - 下に入らなければ上へ反転し、左右も画面内に収める
 *   - 高さは画面の 7 割までにして、あふれたら中でスクロールさせる
 *   - z-index は「開いた場所より上」に自動で合わせる（モーダルの中でも前に出る）
 *   - 画面が狭いときは、下から出るシート（ボトムシート）にする
 *
 *   const popup = createPopup({ anchor: this.$el, panel: this.$refs.panel, sheet: true });
 *   popup.show();  popup.hide();  popup.contains(event.target);
 */

/** アンカーとの隙間 */
const GAP = 4;

/** 画面端に残す余白 */
const MARGIN = 8;

/** これより狭い画面ではボトムシートで開く（Tailwind の sm と同じ） */
const SHEET_BREAKPOINT = 640;

/** ポップアップの基本の重なり順（追従ヘッダー z-30 より上・モーダル z-50 より下） */
const BASE_Z_INDEX = 40;

/**
 * 開いた場所より前に出る z-index を求める。
 *
 * モーダル（z-50）の中で開いた場合など、まわりに重なり順が指定されていれば
 * そのぶん上に積む。
 */
function zIndexFor(anchor) {
    let value = BASE_Z_INDEX;

    for (let parent = anchor.parentElement; parent; parent = parent.parentElement) {
        const style = getComputedStyle(parent);
        const z = Number.parseInt(style.zIndex, 10);

        if (!Number.isNaN(z) && style.position !== 'static') {
            value = Math.max(value, z + 10);
        }
    }

    return value;
}

export default function createPopup({ anchor, panel, matchWidth = false, sheet = false }) {
    let placeholder = null;
    let listening = false;

    const place = () => {
        if (!panel.isConnected) {
            return;
        }

        const viewportWidth = document.documentElement.clientWidth;
        const viewportHeight = window.innerHeight;
        const rect = anchor.getBoundingClientRect();

        // 前回の指定を消してから測る（高さの上限はクラス側の指定を活かしたいので空にする）
        Object.assign(panel.style, {
            maxHeight: '', maxWidth: '', width: '', right: '', bottom: '', top: '', left: '',
        });

        // 狭い画面では、下から出るシートにする（位置合わせが要らず、指も届く）
        if (sheet && viewportWidth < SHEET_BREAKPOINT) {
            Object.assign(panel.style, {
                position: 'fixed',
                left: '0',
                right: '0',
                bottom: '0',
                top: 'auto',
                width: 'auto',
                maxWidth: 'none',
                maxHeight: '70vh',
                overflowY: 'auto',
                borderRadius: '1rem 1rem 0 0',
                paddingBottom: 'max(0.75rem, env(safe-area-inset-bottom))',
            });

            return;
        }

        if (matchWidth) {
            panel.style.width = `${Math.round(rect.width)}px`;
        }

        const natural = panel.getBoundingClientRect();
        const width = Math.min(natural.width, viewportWidth - MARGIN * 2);

        const spaceBelow = viewportHeight - rect.bottom - GAP - MARGIN;
        const spaceAbove = rect.top - GAP - MARGIN;

        // 下に入らなければ上へ反転する
        const flip = natural.height > spaceBelow && spaceAbove > spaceBelow;
        const limit = Math.max(160, Math.min(viewportHeight * 0.7, flip ? spaceAbove : spaceBelow));
        const height = Math.min(natural.height, limit);

        const left = Math.max(MARGIN, Math.min(rect.left, viewportWidth - width - MARGIN));
        const top = flip ? rect.top - GAP - height : rect.bottom + GAP;

        Object.assign(panel.style, {
            position: 'fixed',
            top: `${Math.round(top)}px`,
            left: `${Math.round(left)}px`,
            width: `${Math.round(width)}px`,
        });

        // 入りきらないときだけ高さを抑えて、中でスクロールさせる
        // （部品が持っている上限より低くしたいときだけ触る）
        if (natural.height > limit) {
            panel.style.maxHeight = `${Math.round(limit)}px`;
            panel.style.overflowY = 'auto';
        }
    };

    const listen = () => {
        if (listening) {
            return;
        }

        // 画面のスクロール・器のスクロール（capture）・回転や幅の変化で置き直す
        window.addEventListener('scroll', place, true);
        window.addEventListener('resize', place);
        listening = true;
    };

    const unlisten = () => {
        if (!listening) {
            return;
        }

        window.removeEventListener('scroll', place, true);
        window.removeEventListener('resize', place);
        listening = false;
    };

    return {
        /** body 直下へ移して、画面内に収まる位置に置く */
        show() {
            if (!panel) {
                return;
            }

            if (!placeholder) {
                // 閉じたときに元の位置へ戻せるよう、印を残しておく
                placeholder = document.createComment('popup');
                panel.parentNode?.insertBefore(placeholder, panel);
            }

            panel.style.zIndex = String(zIndexFor(anchor));
            document.body.appendChild(panel);

            place();
            listen();

            // x-show の表示が反映されたあとに測り直す（初回は大きさが取れないため）
            requestAnimationFrame(place);
        },

        /** 元の位置に戻して、指定した見た目を消す */
        hide() {
            unlisten();

            if (!panel) {
                return;
            }

            if (placeholder?.parentNode) {
                placeholder.parentNode.insertBefore(panel, placeholder);
            }

            for (const property of [
                'position', 'top', 'left', 'right', 'bottom',
                'width', 'maxWidth', 'maxHeight', 'overflowY', 'zIndex', 'borderRadius', 'paddingBottom',
            ]) {
                panel.style[property] = '';
            }
        },

        /** その要素がポップアップの中にあるか（外側クリックの判定に使う） */
        contains(target) {
            return !!target && panel?.contains(target);
        },

        reposition: place,
    };
}
