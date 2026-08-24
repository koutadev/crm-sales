/**
 * アプリの外枠（左サイドナビの開閉）。
 *
 * - collapsed : 画面幅 lg 以上での折りたたみ。localStorage に保存して次回も維持する
 * - mobileOpen: lg 未満でのオーバーレイ表示。画面遷移のたびに閉じた状態から始まる
 */
const STORAGE_KEY = 'app-shell.sidebar-collapsed';

export default function appShell() {
    return {
        collapsed: false,
        mobileOpen: false,

        init() {
            try {
                this.collapsed = window.localStorage.getItem(STORAGE_KEY) === '1';
            } catch (error) {
                // プライベートモードなどで localStorage が使えない場合は既定（展開）のまま
                this.collapsed = false;
            }
        },

        toggleCollapsed() {
            this.collapsed = !this.collapsed;

            try {
                window.localStorage.setItem(STORAGE_KEY, this.collapsed ? '1' : '0');
            } catch (error) {
                // 保存できなくても表示上の切り替えは行う
            }
        },

        openMobile() {
            this.mobileOpen = true;
        },

        closeMobile() {
            this.mobileOpen = false;
        },
    };
}
