/**
 * 検索文字列の正規化（App\Support\Ui\SearchText と同じ規則）。
 *
 * 「あおい」で「アオイ商事」を見つけられるように、入力と候補を同じ形に揃える。
 *   - 全角/半角の違いをなくす（NFKC 正規化）
 *   - カタカナをひらがなに寄せる
 *   - 英字は小文字に揃える
 */
export default function normalizeSearchText(value) {
    if (value === null || value === undefined) {
        return '';
    }

    return String(value)
        .normalize('NFKC')
        .replace(/[ァ-ヶ]/g, (char) => String.fromCharCode(char.charCodeAt(0) - 0x60))
        .toLowerCase()
        .trim();
}
