/**
 * 一覧の行クリックで開く詳細モーダル。
 *
 * 中身はサーバから HTML の断片として取ってくる（一覧の HTML を重くしないため）。
 * バリデーションエラーで戻ってきたときは、サーバ側で描画済みの中身がそのまま入る。
 */
export default function masterDetail(config = {}) {
    return {
        content: config.content ?? '',
        loading: false,
        failed: false,

        async load(url) {
            this.loading = true;
            this.failed = false;
            this.content = '';

            window.dispatchEvent(new CustomEvent('open-modal', { detail: 'master-detail' }));

            try {
                const response = await fetch(url, {
                    headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'text/html' },
                });

                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}`);
                }

                this.content = await response.text();
            } catch (error) {
                this.failed = true;
            } finally {
                this.loading = false;
            }
        },
    };
}
