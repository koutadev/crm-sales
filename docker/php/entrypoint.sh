#!/bin/bash
set -e

cd /var/www/html

# Laravel 本体がまだ無い場合(= Docker ファイルだけの状態)は何もしない
if [ -f composer.json ]; then

    # ---- .env ---------------------------------------------------------------
    if [ ! -f .env ] && [ -f .env.example ]; then
        echo "[entrypoint] .env が無いので .env.example からコピーします"
        cp .env.example .env
    fi

    # ---- Composer 依存 ------------------------------------------------------
    if [ ! -d vendor ]; then
        echo "[entrypoint] composer install を実行します (初回のみ・数分かかります)"
        composer install --no-interaction --prefer-dist
    fi

    # ---- APP_KEY ------------------------------------------------------------
    if [ -f .env ] && ! grep -q '^APP_KEY=base64:' .env; then
        echo "[entrypoint] APP_KEY を生成します"
        php artisan key:generate --force
    fi

    # ---- フロントエンドのビルド ---------------------------------------------
    if [ -f package.json ] && [ ! -d node_modules ]; then
        echo "[entrypoint] npm install を実行します (初回のみ・数分かかります)"
        npm install --no-audit --no-fund
    fi
    if [ -f package.json ] && [ ! -f public/build/manifest.json ]; then
        echo "[entrypoint] npm run build を実行します"
        npm run build
    fi

    # ---- 書き込み権限 -------------------------------------------------------
    mkdir -p storage/framework/{cache,sessions,views} storage/logs bootstrap/cache
    chmod -R ug+rw storage bootstrap/cache || true
fi

exec "$@"
