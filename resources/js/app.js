import Alpine from 'alpinejs';

import appShell from './app-shell';
import combobox from './combobox';
import dateRange from './date-range';
import datepicker from './datepicker';
import masterDetail from './master-detail';
import modal, { registerModalTriggers } from './modal';
import registerToastStore from './toast';
import './charts';

window.Alpine = Alpine;

// レイアウト（左サイドナビの開閉）で使う Alpine コンポーネント
Alpine.data('appShell', appShell);

// コンボボックス（入力で候補を絞るセレクト）
Alpine.data('combobox', combobox);

// 日付範囲ピッカー（相対プリセット + カスタム期間）
Alpine.data('dateRange', dateRange);

// カレンダー（日付選択）
Alpine.data('datepicker', datepicker);

// モーダル（フォーカストラップつき）
Alpine.data('modal', modal);

// data-open-modal / data-close-modal のクリックを拾う（Alpine のスコープ外でも開ける）
registerModalTriggers();

// 一覧の行クリックで開く詳細モーダル
Alpine.data('masterDetail', masterDetail);

// トースト通知（Alpine のストア）
registerToastStore(Alpine);

Alpine.start();
