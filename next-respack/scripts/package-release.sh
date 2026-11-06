#!/usr/bin/env bash
# 构建 Next Respack 发行包（共享主机 + VPS）
# 用法：./scripts/package-release.sh
# 输出：release/next-respack-shared-hosting.zip
#       release/next-respack-vps.zip
#       release/SHA256SUMS

set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
export ROOT
RELEASE="${ROOT}/release"
STAGE="$(mktemp -d /tmp/next-respack-release-XXXXXX)"
VERSION="$(sed -n "s/.*'version' => '\([^']*\)'.*/\1/p" "${ROOT}/config/nextrespack.php" | head -1)"

cleanup() {
  rm -rf "${STAGE}"
}
trap cleanup EXIT

echo "==> 1/6 构建前端资源"
cd "${ROOT}"
npm run build

echo "==> 2/6 复制应用并清理开发文件"
BASE="${STAGE}/base/next-respack"
mkdir -p "${BASE}"
rsync -a \
  --exclude='.git' \
  --exclude='.github' \
  --exclude='.idea' \
  --exclude='.vscode' \
  --exclude='.env' \
  --exclude='.env.backup-*' \
  --exclude='.phpunit.result.cache' \
  --exclude='node_modules' \
  --exclude='release' \
  --exclude='tests' \
  --exclude='phpunit.xml' \
  --exclude='package-lock.json' \
  --exclude='AGENTS.md' \
  --exclude='CLAUDE.md' \
  "${ROOT}/" "${BASE}/"

rm -rf "${BASE}/public/hot" "${BASE}/database/database.sqlite"
mkdir -p \
  "${BASE}/storage/app/private/resource-packs" \
  "${BASE}/storage/framework/cache/data" \
  "${BASE}/storage/framework/sessions" \
  "${BASE}/storage/framework/views" \
  "${BASE}/storage/logs" \
  "${BASE}/bootstrap/cache"
rm -f "${BASE}/storage/app/installed.lock" \
      "${BASE}/storage/app/install-state.json" \
      "${BASE}/storage/app/.installer-key" \
      "${BASE}/bootstrap/cache"/*.php \
      "${BASE}/storage/logs"/*.log \
      "${BASE}/storage/framework/sessions"/* \
      "${BASE}/storage/framework/views"/*.php
rm -rf "${BASE}/storage/framework/cache/data"/*

echo "==> 3/6 安装生产依赖（不含开发包）"
cd "${BASE}"
COMPOSER_HOME="${COMPOSER_HOME:-/tmp/composerhome}" \
  composer install --no-dev --optimize-autoloader --no-interaction --no-progress

echo "==> 4/6 生成共享主机包"
SHARED="${STAGE}/shared"
mkdir -p "${SHARED}/public_html"
cp -a "${BASE}" "${SHARED}/next-respack"
cp "${BASE}/deploy/shared-hosting/public_html/index.php" "${SHARED}/public_html/index.php"
cp "${BASE}/deploy/shared-hosting/public_html/.htaccess" "${SHARED}/public_html/.htaccess"
cp -a "${BASE}/public/build" "${SHARED}/public_html/build"

cat > "${SHARED}/READ-ME-FIRST.txt" <<'TXT'
Next Respack 共享主机发行包
============================

1. 将 next-respack/ 上传到 Web 根目录之外（例如 /home/user/next-respack）。
2. 将 public_html/ 中的 index.php、.htaccess、build/ 上传到站点文档根目录。
3. 如目录层级不同，修改 public_html/index.php 中的 $appPath。
4. 设置 next-respack/storage、bootstrap/cache、database 为 775 可写；
   安装期间项目根目录需可写（用于生成 .env）。
5. 在主机面板把 upload_max_filesize / post_max_size 调到 512M / 520M。
6. 浏览器访问站点域名，按 /install 向导完成安装。

详细步骤见 next-respack/docs/INSTALL-SHARED-HOSTING.md。
TXT

echo "==> 5/6 生成 VPS 包"
VPS="${STAGE}/vps"
mkdir -p "${VPS}"
cp -a "${BASE}" "${VPS}/next-respack"

echo "==> 6/6 压缩并生成校验和"
mkdir -p "${RELEASE}"
rm -f "${RELEASE}/next-respack-shared-hosting.zip" \
      "${RELEASE}/next-respack-vps.zip" \
      "${RELEASE}/SHA256SUMS"

cd "${SHARED}"
zip -qr "${RELEASE}/next-respack-shared-hosting.zip" .
cd "${VPS}"
zip -qr "${RELEASE}/next-respack-vps.zip" .

cd "${RELEASE}"
sha256sum next-respack-shared-hosting.zip next-respack-vps.zip > SHA256SUMS

echo
echo "发行包构建完成："
ls -lh "${RELEASE}"
echo "版本：${VERSION}"
