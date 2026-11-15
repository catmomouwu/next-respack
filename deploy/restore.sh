#!/usr/bin/env bash
# Next Respack 恢复脚本（数据库 + 私有资源包 + .env）
# 用法：sudo APP_DIR=/var/www/next-respack ./deploy/restore.sh /var/backups/next-respack/20260101-120000
# 需确认后执行；FORCE=1 可跳过确认。

set -euo pipefail

APP_DIR="${APP_DIR:-/var/www/next-respack}"
SOURCE="${1:-}"

if [[ -z "${SOURCE}" || ! -d "${SOURCE}" ]]; then
  echo "用法：$0 <备份目录>" >&2
  exit 1
fi

if [[ "${FORCE:-0}" != "1" ]]; then
  echo "即将用以下备份覆盖 ${APP_DIR}：${SOURCE}"
  read -r -p "输入 YES 继续：" CONFIRM
  [[ "${CONFIRM}" == "YES" ]] || { echo "已取消。"; exit 1; }
fi

cd "${SOURCE}"
if [[ -f SHA256SUMS ]]; then
  sha256sum -c SHA256SUMS
fi

echo "[1/5] 恢复 .env ..."
cp "${SOURCE}/env.backup" "${APP_DIR}/.env"

set -a
# shellcheck disable=SC1090
source "${APP_DIR}/.env"
set +a

echo "[2/5] 恢复数据库 ..."
if [[ -f "${SOURCE}/database.sql" ]]; then
  if ! command -v mysql >/dev/null 2>&1; then
    echo "未找到 mysql 客户端。" >&2
    exit 1
  fi
  MYSQL_PWD="${DB_PASSWORD:-}" mysql \
    -h "${DB_HOST:-127.0.0.1}" -P "${DB_PORT:-3306}" \
    -u "${DB_USERNAME}" "${DB_DATABASE}" < "${SOURCE}/database.sql"
else
  DB_FILE="${DB_DATABASE:-${APP_DIR}/database/database.sqlite}"
  cp "${SOURCE}/database.sqlite" "${DB_FILE}"
fi

echo "[3/5] 恢复私有资源包 ..."
STORAGE_DIR="${NEXT_RESPACK_STORAGE_ROOT:-${APP_DIR}/storage/app/private/resource-packs}"
mkdir -p "${STORAGE_DIR}"
if [[ -f "${SOURCE}/resource-packs.tar.gz" ]]; then
  tar -xzf "${SOURCE}/resource-packs.tar.gz" -C "${STORAGE_DIR}"
  # 恢复安装器写入的访问保护文件
  printf '<Directory>\n  Require all denied\n</Directory>\nDeny from all\n' > "${STORAGE_DIR}/.htaccess"
  printf '<!doctype html><title>403</title><h1>403</h1>' > "${STORAGE_DIR}/index.html"
fi

echo "[4/5] 修正权限与缓存 ..."
for d in "${APP_DIR}/storage" "${APP_DIR}/bootstrap/cache"; do
  chmod -R 775 "${d}"
done
chmod 600 "${APP_DIR}/.env"

if command -v php >/dev/null 2>&1; then
  php "${APP_DIR}/artisan" config:clear || true
  php "${APP_DIR}/artisan" cache:clear || true
  php "${APP_DIR}/artisan" view:clear || true
fi

echo "[5/5] 完成。请访问站点验证登录与下载。"
