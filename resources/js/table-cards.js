/**
 * 一覧テーブルのモバイル表示（積み上げカード）の下ごしらえ。
 *
 * 画面が狭いときは CSS が行をカードに積み替え、各セルの前に列見出しを出す。
 * その見出しの文字を、ここで <th> から各 <td> の data-label に写す。
 * こうしておくと、一覧を作る側は今までどおり <td> を並べるだけでよい。
 *
 * あとから差し込まれた表（モーダルの中など）にも効かせたいときは
 * window.refreshTableCards() を呼ぶ。
 */
function labelOf(th) {
    // 並び替えのリンクがある場合は、その文字だけを見出しにする（▲▼ は落とす）
    const text = (th.querySelector('a') ?? th).textContent ?? '';

    return text.replace(/[▲▼]/g, '').trim();
}

/** 「操作」列は見出しを出さず、カードの下段にまとめる */
const ACTION_LABELS = ['操作', 'アクション'];

function apply(root = document) {
    for (const table of root.querySelectorAll('table[data-table-cards]')) {
        const labels = [...table.querySelectorAll(':scope > thead th')].map(labelOf);

        for (const row of table.querySelectorAll(':scope > tbody > tr')) {
            [...row.children].forEach((cell, index) => {
                // 「操作」列と、データが無いときの 1 行には見出しを付けない
                if (cell.hasAttribute('data-label') || cell.hasAttribute('data-actions') || cell.colSpan > 1) {
                    return;
                }

                const label = labels[index] ?? '';

                if (label === '') {
                    return;
                }

                if (ACTION_LABELS.includes(label)) {
                    cell.setAttribute('data-actions', '');

                    return;
                }

                cell.setAttribute('data-label', label);
            });
        }
    }
}

export default function registerTableCards() {
    window.refreshTableCards = apply;

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => apply(), { once: true });

        return;
    }

    apply();
}
