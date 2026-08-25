/**
 * コンボボックス（入力で候補を絞るセレクト）。
 *
 * 2 つのモードがある。
 *   - 静的  : あらかじめ渡した候補をブラウザ側で絞り込む（候補が数十〜数百件まで）
 *   - 非同期: 入力のたびにサーバへ問い合わせて候補を受け取る（source に URL を渡す）
 *
 * 非同期モードのエンドポイントは `?q=<入力文字>` を受け取り、
 * [{ value, label }] の配列（または { data: [...] }）を返す。
 */
import normalizeSearchText from './search-text';

const DEBOUNCE_MS = 250;

export default function combobox(config = {}) {
    return {
        // 候補
        options: config.options ?? [],
        filtered: [],
        source: config.source ?? null,

        // 選択状態
        value: config.value ?? '',
        label: config.label ?? '',
        query: config.label ?? '',

        // 表示状態
        open: false,
        activeIndex: -1,
        loading: false,
        failed: false,
        fetched: false,
        timer: null,

        init() {
            this.filtered = this.options;
            this.syncLabel();

            // 外側と x-modelable で結んでいる場合、値は後から入ってくることがある
            this.$watch('value', () => this.syncLabel());
        },

        /**
         * 候補を差し替える（連動する選択肢：顧客を選ぶと先方担当が変わる、など）。
         *
         * [{ value, label }] でも [{ id, name }] でも受け取れる。
         */
        setOptions(items) {
            this.options = (items ?? []).map((item) => {
                const value = String(item.value ?? item.id ?? '');
                const label = String(item.label ?? item.name ?? '');

                return { value, label, search: item.search ?? normalizeSearchText(label) };
            });

            // 選択中の候補が新しい一覧から消えていたら、選択も外す
            if (this.hasSelection && !this.options.some((option) => option.value === String(this.value))) {
                this.value = '';
                this.label = '';
                this.query = '';
            }

            if (!this.isAsync) {
                this.filtered = this.filter(this.options, this.open ? this.query : '');
            }

            this.syncLabel();
        },

        /** 選択中の値に対応するラベルを候補から引き直す */
        syncLabel() {
            if (!this.hasSelection) {
                this.label = '';

                if (!this.open) {
                    this.query = '';
                }

                return;
            }

            const found = this.options.find((option) => option.value === String(this.value));

            if (!found) {
                return;
            }

            this.label = found.label;

            if (!this.open) {
                this.query = found.label;
            }
        },

        get isAsync() {
            return this.source !== null && this.source !== '';
        },

        get hasSelection() {
            return this.value !== '' && this.value !== null;
        },

        /** 現在ハイライトしている候補の id（aria-activedescendant 用） */
        activeDescendant(prefix) {
            return this.activeIndex >= 0 ? `${prefix}-option-${this.activeIndex}` : null;
        },

        openList() {
            if (this.open) {
                return;
            }

            this.open = true;
            this.activeIndex = -1;

            // 非同期モードは開いた時点で一度読み込む
            // (選択済みの 1 件だけを持っている場合もあるので、取得済みかどうかで判断する)
            if (this.isAsync && !this.fetched) {
                this.fetchOptions();
            }
        },

        /**
         * 閉じる。選択せずに閉じた場合は入力欄を選択中のラベルに戻す。
         */
        close() {
            this.open = false;
            this.activeIndex = -1;
            this.query = this.label;
        },

        onInput() {
            this.open = true;
            this.activeIndex = -1;

            if (this.isAsync) {
                this.debouncedFetch();

                return;
            }

            this.filtered = this.filter(this.options, this.query);
        },

        /**
         * ひらがな・カタカナ・全角半角・大文字小文字の違いを無視して絞り込む。
         * 候補側の正規化済み文字列(search)はサーバ側で作って渡している。
         */
        filter(options, query) {
            const needle = normalizeSearchText(query);

            if (needle === '') {
                return options;
            }

            return options.filter((option) => {
                const haystack = option.search ?? normalizeSearchText(option.label);

                return haystack.includes(needle);
            });
        },

        debouncedFetch() {
            clearTimeout(this.timer);
            this.timer = setTimeout(() => this.fetchOptions(), DEBOUNCE_MS);
        },

        async fetchOptions() {
            this.loading = true;
            this.failed = false;

            try {
                const url = new URL(this.source, window.location.origin);
                url.searchParams.set('q', this.query ?? '');

                const response = await fetch(url, { headers: { Accept: 'application/json' } });

                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}`);
                }

                const payload = await response.json();

                this.filtered = Array.isArray(payload) ? payload : (payload.data ?? []);
                this.fetched = true;
            } catch (error) {
                this.filtered = [];
                this.failed = true;
            } finally {
                this.loading = false;
            }
        },

        select(item) {
            this.value = item.value;
            this.label = item.label;
            this.query = item.label;
            this.open = false;
            this.activeIndex = -1;

            this.$dispatch('combobox-selected', { value: item.value, label: item.label });
        },

        selectActive() {
            const item = this.filtered[this.activeIndex];

            if (item) {
                this.select(item);
            }
        },

        clear() {
            this.value = '';
            this.label = '';
            this.query = '';
            this.filtered = this.isAsync ? [] : this.options;
            this.fetched = false;
            this.activeIndex = -1;

            this.$dispatch('combobox-selected', { value: '', label: '' });
        },

        /** 上下キーでの移動（端では折り返す） */
        move(step) {
            if (!this.open) {
                this.openList();
            }

            if (this.filtered.length === 0) {
                return;
            }

            this.activeIndex = (this.activeIndex + step + this.filtered.length) % this.filtered.length;
            this.scrollIntoView();
        },

        moveTo(index) {
            if (this.filtered.length === 0) {
                return;
            }

            this.open = true;
            this.activeIndex = index < 0 ? this.filtered.length - 1 : index;
            this.scrollIntoView();
        },

        scrollIntoView() {
            this.$nextTick(() => {
                const list = this.$refs.list;
                const option = list?.children[this.activeIndex];

                option?.scrollIntoView({ block: 'nearest' });
            });
        },
    };
}
