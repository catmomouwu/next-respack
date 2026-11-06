#!/usr/bin/env bash
# Next Respack 备份脚本（数据库 + 私有资源包 + .env）
# 用法：sudo APP_DIR=/var/www/next-respack BACKUP_DIR=/var/backups/next-respack ./deploy/backup.sh

set -euo pipefail

APP_DIR="${APP_DIR:-/var/www/next-respack}"
BACKUP_DIR="${BACKUP_DIR:-/var/backups/next-respack}"
STAMP="$(date +%Y%m%d-%H%M%S)"
TARGET="${BACKUP_DIR}/${STAMP}"

if [[ ! -f "${APP_DIR}/.env" ]]; then
  echo "未找到 ${APP_DIR}/.env，请检查 APP_DIR。" >&2
  exit 1
fi

mkdir -p "${TARGET}"

# 读取 .env（忽略注释与空行）
set -a
# shellcheck disable=SC1090
source "${APP_DIR}/.env"
set +a

DB_CONNECTION="${DB_CONNECTION:-mysql}"
STORAGE_DIR="${NEXT_RESPACK_STORAGE_ROOT:-${APP_DIR}/storage/app/private/resource-packs}"

echo "[1/4] 备份数据库（${DB_CONNECTION}）..."
case "${DB_CONNECTION}" in
  sqlite)
    DB_FILE="${DB_DATABASE:-${APP_DIR}/database/database.sqlite}"
    if [[ ! -f "${DB_FILE}" ]]; then
      echo "SQLite 数据库不存在：${DB_FILE}" >&2
      exit 1
    fi
    if command -v sqlite3 >/dev/null 2>&1; then
      sqlite3 "${DB_FILE}" ".backup '${TARGET}/database.sqlite'"
    else
      cp "${DB_FILE}" "${TARGET}/database.sqlite"
    fi
    ;;
  mysql|mariadb)
    if ! command -v mysqldump >/dev/null 2>&1; then
      echo "未找到 mysqldump，请安装 mysql-client。" >&2
      exit 1
    fi
    MYSQL_PWD="${DB_PASSWORD:-}" mysqldump \
      --single-transaction --routines --triggers --no-tablespaces \
      -h "${DB_HOST:-127.0.0.1}" -P "${DB_PORT:-3306}" \
      -u "${DB_USERNAME}" "${DB_DATABASE}" > "${TARGET}/database.sql"
    ;;
  *)
    echo "不支持的数据库类型：${DB_CONNECTION}" >&2
    exit 1
    ;;
esac

echo "[2/4] 备份私有资源包..."
if [[ -d "${STORAGE_DIR}" ]]; then
  tar -czf "${TARGET}/resource-packs.tar.gz" -C "${STORAGE_DIR}" .
else
  echo "警告：存储目录不存在，已跳过：${STORAGE_DIR}" >&2
fi

echo "[3/4] 备份 .env 与安装锁..."
cp "${APP_DIR}/.env" "${TARGET}/env.backup"
for f in installed.lock install-state.json; do
  [[ -f "${APP_DIR}/storage/app/${f}" ]] && cp "${APP_DIR}/storage/app/${f}" "${TARGET}/${f}"
done

echo "[4/4] 生成校验和..."
cd "${TARGET}"
sha256sum ./* > SHA256SUMS

echo "备份完成：${TARGET}"
