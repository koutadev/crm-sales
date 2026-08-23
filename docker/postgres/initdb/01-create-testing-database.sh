#!/bin/bash
# テスト用データベースを作成する。
# PostgreSQL の公式イメージは初回起動時(ボリュームが空のとき)にのみ実行する。
set -e

psql -v ON_ERROR_STOP=1 --username "$POSTGRES_USER" --dbname "$POSTGRES_DB" <<-EOSQL
    CREATE DATABASE "${POSTGRES_DB}_testing" OWNER "$POSTGRES_USER";
EOSQL

echo "[initdb] created database ${POSTGRES_DB}_testing"
