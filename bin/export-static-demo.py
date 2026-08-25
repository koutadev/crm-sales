#!/usr/bin/env python3
"""
本体（crm-sales）の実画面を静的サイトとして書き出す。

    docker compose up -d                       # 本体を起動しておく
    python3 bin/export-static-demo.py          # ../crm-demo-static/public へ書き出す
    python3 bin/export-static-demo.py --out /path/to/dir --base http://localhost:8080

やっていること:
  1. デモ用アカウントでログインして、対象ページの HTML を取得する（Blade 描画後の実物）
  2. ページ間のリンクを静的ファイル名へ張り替え、それ以外の遷移・送信は「デモでは動作しません」に倒す
  3. ビルド済みアセット（public/build）をそのまま持っていく
  4. デモの注記バナーと、無効化用の CSS / JS を差し込む

本体を更新したら、このスクリプトを流し直せば静的デモも追随する。
"""

from __future__ import annotations

import argparse
import http.cookiejar
import re
import shutil
import sys
import urllib.error
import urllib.parse
import urllib.request
from pathlib import Path

# 書き出すページ（ファイル名 → 本体のパス）
PAGES: list[tuple[str, str, str]] = [
    ("dashboard.html", "/dashboard", "ダッシュボード"),
    ("deals.html", "/deals?reset=1", "商談一覧"),
    ("deals-kanban.html", "/deals?view_mode=kanban", "商談カンバン"),
    ("deal.html", "/deals/32", "商談詳細"),
    ("customer.html", "/customers/44", "顧客詳細"),
    ("masters.html", "/masters", "マスタ管理"),
]

# 本体のパス → 静的ファイル名（クエリは無視して判定する）
STATIC_ROUTES: list[tuple[re.Pattern[str], str]] = [
    (re.compile(r"^/dashboard$"), "dashboard.html"),
    (re.compile(r"^/deals$"), "deals.html"),          # view_mode=kanban は別扱い（下で判定）
    (re.compile(r"^/deals/\d+$"), "deal.html"),
    (re.compile(r"^/customers/\d+$"), "customer.html"),
    (re.compile(r"^/masters$"), "masters.html"),
]

# 書き換えの対象にしないもの(アセット)
ASSET_PATH = re.compile(r"^/?build/|\.(css|js|mjs|woff2?|ttf|png|jpe?g|gif|svg|ico|webp)$")

BANNER = """
<div class="demo-banner" role="note">
    <span class="demo-banner__tag">DEMO</span>
    <span>
        これはポートフォリオ用の静的デモです。表示されている会社名・担当者名・金額は
        <strong>すべて架空のダミーデータ</strong>で、保存・出力・検索などの操作は動作しません。
    </span>
</div>
"""

DEMO_CSS = """/* 静的デモ用の追記。本体のスタイルには手を入れない。 */
.demo-banner {
    position: sticky;
    top: 0;
    z-index: 60;
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.5rem 1rem;
    background: #1f2937;
    color: #f9fafb;
    font-size: 0.75rem;
    line-height: 1.5;
}

.demo-banner strong { color: #fdba74; font-weight: 600; }

.demo-banner__tag {
    flex-shrink: 0;
    border-radius: 9999px;
    background: #f97316;
    padding: 0.125rem 0.5rem;
    font-weight: 700;
    letter-spacing: 0.05em;
}

/* 動作しない操作は、押せないことが見て分かるようにする */
[data-demo-disabled] {
    cursor: not-allowed !important;
    opacity: 0.65;
}

.demo-notice {
    position: fixed;
    left: 50%;
    bottom: 1.5rem;
    z-index: 80;
    transform: translateX(-50%);
    max-width: 90vw;
    border-radius: 0.5rem;
    background: #1f2937;
    color: #f9fafb;
    padding: 0.625rem 1rem;
    font-size: 0.8125rem;
    box-shadow: 0 10px 25px rgb(0 0 0 / 0.25);
}

@media (prefers-reduced-motion: no-preference) {
    .demo-notice { transition: opacity 0.2s ease; }
}
"""

DEMO_JS = """/* 静的デモ用。動かない操作を「動きません」と伝えるだけの薄い層。 */
(function () {
    'use strict';

    var MESSAGE = 'このデモでは動作しません（表示専用の静的サイトです）。';

    function notice(message) {
        var el = document.querySelector('.demo-notice');

        if (! el) {
            el = document.createElement('div');
            el.className = 'demo-notice';
            el.setAttribute('role', 'status');
            document.body.appendChild(el);
        }

        el.textContent = message || MESSAGE;
        el.style.opacity = '1';

        clearTimeout(el.dataset.timer);
        el.dataset.timer = setTimeout(function () { el.style.opacity = '0'; }, 2600);
    }

    // 保存・検索の通信は行わない（非同期コンボボックス、カンバンのステータス更新など）
    window.fetch = function () {
        notice('このデモでは通信を行いません（検索・保存は無効です）。');

        return Promise.reject(new Error('static demo'));
    };

    document.addEventListener('DOMContentLoaded', function () {
        // ドラッグ&ドロップでのステータス変更は無効にする
        document.querySelectorAll('[draggable="true"]').forEach(function (card) {
            card.setAttribute('draggable', 'false');
            card.setAttribute('data-demo-disabled', 'drag');
            card.title = 'このデモではドラッグでのステータス変更はできません。';
        });

        // 送信は行わない（検索・保存ビュー・活動の追加・削除など）
        document.querySelectorAll('form').forEach(function (form) {
            form.setAttribute('data-demo-disabled', 'form');
            form.addEventListener('submit', function (event) {
                event.preventDefault();
                notice('このデモでは保存・検索は行いません。');
            });
        });
    }, { once: true });

    // 静的サイトに無い遷移先はここで止める
    document.addEventListener('click', function (event) {
        var target = event.target.closest('a[data-demo-disabled], button[data-demo-disabled]');

        if (! target) {
            return;
        }

        event.preventDefault();
        notice(target.dataset.demoMessage || MESSAGE);
    });
})();
"""

INDEX_REDIRECT = """<!DOCTYPE html>
<html lang="ja">
    <head>
        <meta charset="utf-8">
        <title>アルティス営業管理（デモ）</title>
        <meta http-equiv="refresh" content="0; url=dashboard.html">
        <link rel="canonical" href="dashboard.html">
    </head>
    <body>
        <p><a href="dashboard.html">ダッシュボードへ</a></p>
    </body>
</html>
"""

VERCEL_JSON = """{
  "$schema": "https://openapi.vercel.sh/vercel.json",
  "cleanUrls": false,
  "trailingSlash": false
}
"""


class Exporter:
    def __init__(self, base: str, out: Path, project: Path, email: str, password: str) -> None:
        self.base = base.rstrip("/")
        self.out = out
        self.project = project
        self.email = email
        self.password = password
        self.jar = http.cookiejar.CookieJar()
        self.opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(self.jar))

    # --- 取得 -------------------------------------------------------------

    def get(self, path: str) -> str:
        request = urllib.request.Request(self.base + path, headers={"User-Agent": "static-demo-export"})

        with self.opener.open(request, timeout=30) as response:
            return response.read().decode("utf-8")

    def login(self) -> None:
        html = self.get("/login")
        token = re.search(r'name="_token" value="([^"]+)"', html)

        if token is None:
            raise SystemExit("ログイン画面から CSRF トークンを取得できませんでした。")

        data = urllib.parse.urlencode({
            "_token": token.group(1),
            "email": self.email,
            "password": self.password,
        }).encode()

        request = urllib.request.Request(self.base + "/login", data=data, headers={"User-Agent": "static-demo-export"})

        with self.opener.open(request, timeout=30) as response:
            body = response.read().decode("utf-8")

        if "ログアウト" not in body and "サインアウト" not in body and "/dashboard" not in body:
            raise SystemExit("ログインに失敗しました（メールアドレスとパスワードを確認してください）。")

    # --- 書き換え ---------------------------------------------------------

    def static_name_for(self, url: str) -> str | None:
        """本体の URL に対応する静的ファイル名（無ければ None）。"""
        parsed = urllib.parse.urlparse(url)
        path = parsed.path.rstrip("/") or "/"

        if path == "/deals" and "view_mode=kanban" in (parsed.query or ""):
            return "deals-kanban.html"

        for pattern, name in STATIC_ROUTES:
            if pattern.match(path):
                return name

        return None

    def rewrite_links(self, html: str) -> str:
        """href / action の行き先を、静的ファイルか「動作しません」に振り分ける。"""

        def replace(match: re.Match[str]) -> str:
            attribute, quote, url = match.group(1), match.group(2), match.group(3)

            if url.startswith("#") or url.startswith("mailto:") or url.startswith("tel:"):
                return match.group(0)

            # アセット(CSS / JS / フォントなど)はそのまま残す
            if ASSET_PATH.search(urllib.parse.urlparse(url).path):
                return match.group(0)

            absolute = url.startswith(self.base)

            if not absolute and (url.startswith("http://") or url.startswith("https://")):
                return match.group(0)   # 外部リンクはそのまま

            name = self.static_name_for(url if absolute else urllib.parse.urljoin(self.base, url))

            if attribute == "href" and name is not None:
                return f'href={quote}{name}{quote}'

            # 静的サイトに無い遷移・送信先
            return f'{attribute}={quote}#{quote} data-demo-disabled="link"'

        html = re.sub(r'\b(href|action)=(["\'])([^"\']*)\2', replace, html)

        # 残った絶対 URL（JS の中など）も相対化しておく
        return html.replace(self.base + "/", "").replace(self.base, "")

    def scrub(self, html: str) -> str:
        """セッション由来の値(CSRF トークン)は残さない。"""
        html = re.sub(r'(name="csrf-token" content=")[^"]*(")', r"\1\2", html)

        return re.sub(r'(name="_token" value=")[^"]*(")', r"\1\2", html)

    def inject(self, html: str, title: str) -> str:
        html = html.replace(
            "</head>",
            '    <link rel="stylesheet" href="demo.css">\n'
            '    <script src="demo.js"></script>\n'
            "</head>",
            1,
        )

        html = html.replace("<body", '<body data-demo-page="' + title + '"', 1)

        # バナーは body の直後（ページ内のどこにいても見える位置）
        return re.sub(r"(<body[^>]*>)", lambda m: m.group(1) + BANNER, html, count=1)

    # --- 出力 -------------------------------------------------------------

    def export(self) -> None:
        self.out.mkdir(parents=True, exist_ok=True)

        self.login()

        for name, path, title in PAGES:
            html = self.get(path)
            html = self.rewrite_links(html)
            html = self.scrub(html)
            html = self.inject(html, title)

            (self.out / name).write_text(html, encoding="utf-8")
            print(f"  書き出し: {name:22} ← {path}  ({title})")

        (self.out / "index.html").write_text(INDEX_REDIRECT, encoding="utf-8")
        (self.out / "demo.css").write_text(DEMO_CSS, encoding="utf-8")
        (self.out / "demo.js").write_text(DEMO_JS, encoding="utf-8")

        build_src = self.project / "public" / "build"
        build_dest = self.out / "build"

        if not build_src.is_dir():
            raise SystemExit("public/build がありません。先に npm run build を実行してください。")

        if build_dest.exists():
            shutil.rmtree(build_dest)

        shutil.copytree(build_src, build_dest)
        print(f"  アセット: build/ ({sum(1 for _ in build_dest.rglob('*') if _.is_file())} ファイル)")

        favicon = self.project / "public" / "favicon.ico"

        if favicon.exists():
            shutil.copy2(favicon, self.out / "favicon.ico")


def main() -> int:
    project = Path(__file__).resolve().parent.parent

    parser = argparse.ArgumentParser(description="本体の実画面を静的デモとして書き出す")
    parser.add_argument("--base", default="http://localhost:8080", help="本体の URL")
    parser.add_argument("--out", default=str(project.parent / "crm-demo-static"), help="書き出し先")
    parser.add_argument("--email", default="admin@example.com")
    parser.add_argument("--password", default="password")
    args = parser.parse_args()

    out = Path(args.out).resolve()

    print(f"本体 : {args.base}")
    print(f"出力 : {out}")

    try:
        Exporter(args.base, out, project, args.email, args.password).export()
    except urllib.error.URLError as error:
        raise SystemExit(f"本体に接続できません（docker compose up -d は済んでいますか）: {error}")

    # Vercel はこのディレクトリをそのまま配信する
    (out / "vercel.json").write_text(VERCEL_JSON, encoding="utf-8")

    print("\n完了しました。ローカル確認:")
    print(f"  python3 -m http.server 4173 --directory {out}")

    return 0


if __name__ == "__main__":
    sys.exit(main())
