/**
 * モバイル幅の横断監査。
 *
 * 各画面と「部品を開いた状態」を、実際のブラウザで測って記録する。
 *   - 文書の横あふれ／画面外にはみ出している要素
 *   - ポップアップ（カレンダー・期間・候補一覧）の位置・重なり順・内部スクロール
 *   - 44px に満たないタップ領域
 *
 *   # 1) 対象のアプリを起動しておく
 *   docker compose up -d
 *
 *   # 2) 撮影の道具を、プロジェクトの外に用意する（node_modules を汚さない）
 *   mkdir -p /tmp/mobile-audit && (cd /tmp/mobile-audit && npm install --no-save puppeteer-core)
 *
 *   # 3) 監査（ホストの Chrome を使う）
 *   PUPPETEER_HOME=/tmp/mobile-audit node bin/mobile-audit.mjs
 *   PUPPETEER_HOME=/tmp/mobile-audit node bin/mobile-audit.mjs --extra=/lessons,/reservations
 *
 * オプション（すべて任意）
 *   --base=http://localhost:8080     対象の URL
 *   --email=admin@example.com        ログインするユーザー（--password も指定可）
 *   --widths=390,320                 確かめる画面幅
 *   --extra=/a,/b                    この製品だけの画面を足す
 *   --out=storage/app/mobile-audit   出力先（JSON とスクリーンショット）
 */

import { mkdir, writeFile } from 'node:fs/promises';
import { createRequire } from 'node:module';
import { resolve } from 'node:path';

const require = createRequire(
    process.env.PUPPETEER_HOME ? `${process.env.PUPPETEER_HOME}/` : import.meta.url,
);
const puppeteer = require('puppeteer-core');

const option = (name, fallback) => {
    const hit = process.argv.find((arg) => arg.startsWith(`--${name}=`));

    return hit ? hit.slice(name.length + 3) : fallback;
};

const BASE = option('base', process.env.BASE_URL ?? 'http://localhost:8080').replace(/\/$/, '');
const EMAIL = option('email', 'admin@example.com');
const PASSWORD = option('password', 'password');
const WIDTHS = option('widths', '390,320').split(',').map((value) => Number.parseInt(value, 10));
const EXTRA = option('extra', '').split(',').filter(Boolean);
const OUT = resolve(option('out', 'storage/app/mobile-audit'));
const CHROME = process.env.CHROME_PATH
    ?? '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome';

/** 共通基盤にある画面。無い画面（404 / 403）は自動で飛ばす。 */
const SCREENS = [
    '/dashboard',
    '/masters',
    '/masters/employees',
    '/masters/employees/create',
    '/users',
    '/_ui',
    ...EXTRA,
];

/** 開いた状態を確かめるポップアップ（開くボタン → 中身の目印） */
const POPUPS = [
    { id: 'datepicker', open: 'button[aria-label="カレンダーを開く"]', panel: 'div[role="dialog"][aria-label="日付を選ぶ"]' },
    { id: 'date-range', open: '[x-data^="dateRange"] button', panel: 'div[role="dialog"][aria-label="期間を選ぶ"]' },
    { id: 'combobox', open: 'input[role="combobox"]', panel: 'ul[role="listbox"]' },
];

const wait = (ms) => new Promise((done) => setTimeout(done, ms));

/** 画面の横あふれと、画面外へ出ている要素（いちばん外側だけ） */
const MEASURE = `(() => {
    const label = (el) => {
        const cls = typeof el.className === 'string' ? el.className.trim().split(/\\s+/).slice(0, 3).join('.') : '';
        return (el.tagName.toLowerCase() + (el.id ? '#' + el.id : '') + (cls ? '.' + cls : '')).slice(0, 90);
    };
    const vw = window.innerWidth;
    const all = [...document.querySelectorAll('body *')];
    const over = all.filter((el) => {
        const r = el.getBoundingClientRect();
        const st = getComputedStyle(el);
        if (r.width < 2 || r.height < 2 || st.visibility === 'hidden' || st.display === 'none') return false;
        return r.right > vw + 1 || r.left < -1;
    });
    const outer = over.filter((el) => !over.includes(el.parentElement));
    // 指で押す部品は 44px 四方が目安（iOS / Android のガイドライン）。
    // 文字のあるボタン・リンクは横幅が文言の長さで決まるので、高さだけを見る。
    // 記号やアイコンだけ（‹ › ✕ など、文字も数字も含まないもの）は横幅も 44px を求める。
    const taps = [...document.querySelectorAll('a, button, [role="link"], select')].filter((el) => {
        const r = el.getBoundingClientRect();
        if (r.width === 0 || r.height === 0) return false;

        const iconOnly = !/[\\p{L}\\p{N}]/u.test(el.textContent);

        return r.height < 44 || (iconOnly ? r.width < 44 : r.width < 24);
    });
    return {
        horizontalOverflow: document.documentElement.scrollWidth - vw,
        offenders: outer.slice(0, 8).map((el) => ({ sel: label(el), overflowRight: Math.round(el.getBoundingClientRect().right - vw) })),
        smallTapTargets: taps.length,
        smallTapExamples: taps.slice(0, 8).map((el) => {
            const r = el.getBoundingClientRect();
            return label(el) + ' 「' + (el.textContent || '').replace(/\s+/g, ' ').trim().slice(0, 12) + '」 '
                + Math.round(r.width) + 'x' + Math.round(r.height);
        }),
    };
})()`;

/** 開いたポップアップの様子 */
const inspect = (selector) => `(() => {
    // body 直下へ移したパネルも拾えるよう、「見えているもの」を選ぶ
    const el = [...document.querySelectorAll(${JSON.stringify(selector)})]
        .find((node) => node.getBoundingClientRect().height > 1);
    if (! el) return { found: false };
    const r = el.getBoundingClientRect();
    const st = getComputedStyle(el);
    const clippers = [];
    for (let p = el.parentElement; p && p !== document.body; p = p.parentElement) {
        const s = getComputedStyle(p);
        if (s.overflowX !== 'visible' || s.overflowY !== 'visible') {
            clippers.push((String(p.className).split(/\\s+/).slice(0, 3).join('.') || p.tagName) + ' [' + s.overflowX + '/' + s.overflowY + ']');
        }
    }
    return {
        found: true,
        portaled: el.parentElement === document.body,
        position: st.position,
        zIndex: st.zIndex,
        scrollable: st.overflowY === 'auto' || st.overflowY === 'scroll',
        rect: [Math.round(r.left), Math.round(r.top), Math.round(r.width), Math.round(r.height)],
        outRight: Math.round(r.right - window.innerWidth),
        outLeft: Math.round(-r.left),
        outBottom: Math.round(r.bottom - window.innerHeight),
        clippedBy: clippers.slice(0, 2),
    };
})()`;

async function login(page) {
    await page.goto(`${BASE}/login`, { waitUntil: 'networkidle0' });

    if (page.url().includes('/login')) {
        await page.type('#email', EMAIL);
        await page.type('#password', PASSWORD);
        await Promise.all([
            page.waitForNavigation({ waitUntil: 'networkidle0' }),
            page.click('button[type="submit"]'),
        ]);
    }
}

const browser = await puppeteer.launch({
    executablePath: CHROME,
    headless: 'new',
    args: ['--hide-scrollbars'],
    protocolTimeout: 30_000,
});

await mkdir(OUT, { recursive: true });

const results = [];

for (const width of WIDTHS) {
    const context = await browser.createBrowserContext();
    const page = await context.newPage();

    page.setDefaultTimeout(30_000);
    await page.setViewport({ width, height: 844, deviceScaleFactor: 2, isMobile: true, hasTouch: true });
    await login(page);

    console.log(`\n=== ${width}px ===`);

    for (const path of SCREENS) {
        const response = await page.goto(BASE + path, { waitUntil: 'networkidle0' }).catch(() => null);
        const status = response?.status() ?? 0;

        if (status >= 400) {
            console.log(`  ${path.padEnd(26)} (${status} なので飛ばす)`);
            continue;
        }

        await wait(300);

        const measure = await page.evaluate(MEASURE);
        const name = `${width}-${path.replaceAll('/', '_') || '_root'}`;

        await page.screenshot({ path: `${OUT}/${name}.png` });

        results.push({ width, path, ...measure });
        console.log(
            `  ${path.padEnd(26)} 横あふれ=${measure.horizontalOverflow}px  はみ出し=${measure.offenders.length}  小さいタップ領域=${measure.smallTapTargets}`,
        );

        for (const popup of POPUPS) {
            const opened = await page.evaluate((selector) => {
                const trigger = document.querySelector(selector);

                if (! trigger) return false;

                trigger.scrollIntoView({ block: 'center' });
                // コンボボックスはフォーカスで開くので、両方おこなう
                trigger.focus?.();
                trigger.click();

                return true;
            }, popup.open).catch(() => false);

            if (! opened) continue;

            await wait(500);

            const state = await page.evaluate(inspect(popup.panel));

            if (! state.found) continue;

            await page.screenshot({ path: `${OUT}/${name}-${popup.id}.png` });
            results.push({ width, path, popup: popup.id, ...state });

            const flags = [
                state.outBottom > 0 ? `下に${state.outBottom}px` : null,
                state.outRight > 0 ? `右に${state.outRight}px` : null,
                state.outLeft > 0 ? `左に${state.outLeft}px` : null,
                state.clippedBy.length > 0 && ! state.portaled ? '器で切られる' : null,
                // 入りきっていないのにスクロールできない場合だけ知らせる
                state.outBottom > 0 && ! state.scrollable ? '内部スクロールなし' : null,
            ].filter(Boolean);

            console.log(
                `    └ ${popup.id.padEnd(11)} z=${state.zIndex} ${state.portaled ? 'body直下' : '入れ子  '} ` +
                `${state.rect[2]}x${state.rect[3]} ${flags.length ? '⚠ ' + flags.join(' / ') : '✓ 収まっている'}`,
            );

            await page.keyboard.press('Escape').catch(() => {});
            await wait(200);
        }
    }

    await context.close();
}

await browser.close();
await writeFile(`${OUT}/results.json`, JSON.stringify(results, null, 2), 'utf8');
console.log(`\n結果: ${OUT}/results.json（スクリーンショットも同じ場所）`);
