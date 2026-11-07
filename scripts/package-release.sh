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

# Keep empty runtime directories in the archive.
for dir in \
  "${BASE}/storage/app/private/resource-packs" \
  "${BASE}/storage/framework/cache/data" \
  "${BASE}/storage/framework/sessions" \
  "${BASE}/storage/framework/views" \
  "${BASE}/storage/logs" \
  "${BASE}/bootstrap/cache"; do
  touch "${dir}/.gitkeep"
done

# Defense in depth for the private pack directory.
printf '<Directory>\n  Require all denied\n</Directory>\nDeny from all\n' \
  > "${BASE}/storage/app/private/resource-packs/.htaccess"
printf '<!doctype html><title>403</title><h1>403 Forbidden</h1>' \
  > "${BASE}/storage/app/private/resource-packs/index.html"

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
cp "${BASE}/deploy/shared-hosting/public_html/.user.ini" "${SHARED}/public_html/.user.ini"
cp -a "${BASE}/public/build" "${SHARED}/public_html/build"
cp -a "${BASE}/public/favicon.ico" "${SHARED}/public_html/favicon.ico" 2>/dev/null || true
cp -a "${BASE}/public/robots.txt" "${SHARED}/public_html/robots.txt" 2>/dev/null || true

# A .env with no secrets lets the installer start even on hosts where the
# project root is read-only. It is rewritten during installation.
cp "${BASE}/.env.example" "${SHARED}/next-respack/.env"

cat > "${SHARED}/部署说明.txt" <<'TXT'
Next Respack 共享主机版（解压即用）
====================================

一、放到哪里
------------
把本压缩包解压到「包含 public_html 的那个目录」，解压后应当是：

  你的账号目录/
  ├── next-respack/     应用（不要直接访问）
  └── public_html/      网站根目录（index.php、.htaccess、build/）

注意：
* 如果你的主机网站根目录叫 www、httpdocs 或 wwwroot，
  请把 public_html 文件夹改名为对应名称，无需改任何代码。
* 子域名 / 附加域名的主机，请解压到该域名的目录
  （即包含其 public_html 的那一层）。

二、设置权限
------------
在主机文件管理器中把下面目录设为 775（可写）：

  next-respack/storage
  next-respack/bootstrap/cache
  next-respack/database

如果主机允许，把 next-respack/.env 设为 600。
安装向导需要写 .env；若根目录不可写，请先让 .env 可写。

三、创建数据库
--------------
在主机面板新建 MySQL / MariaDB 数据库与用户，记下：
主机、端口、数据库名、用户名、密码。

四、访问安装
------------
浏览器打开你的域名，会自动进入 /install 向导：

  1. 环境检测
  2. 数据库（填上面的信息，点“测试连接”）
  3. 存储（默认本地存储即可，点“测试”）
  4. 站点信息（公开域名必须用 https://）
  5. 创建超级管理员
  6. 确认并执行安装

安装完成后 /install 自动失效。

五、上传大小
------------
本包已附带 public_html/.user.ini（512M 上传上限）。
若主机忽略 .user.ini，请在控制面板把：
  upload_max_filesize = 512M
  post_max_size       = 520M
  memory_limit        = 256M
  max_execution_time  = 300

六、环境要求
------------
PHP 8.3+，扩展：ctype、curl、dom、fileinfo、filter、hash、json、
mbstring、openssl、pcre、pdo、pdo_mysql、session、tokenizer、xml、zip。

七、遇到问题
------------
先看 next-respack/storage/logs/laravel.log，
更多说明见 next-respack/docs/TROUBLESHOOTING.md。
TXT

cat > "${SHARED}/READ-ME-FIRST.txt" <<'TXT'
Next Respack - Shared hosting package
=====================================

Extract this archive into the directory that CONTAINS your web root:

  account/
  ├── next-respack/   application (not web accessible)
  └── public_html/    document root (index.php, .htaccess, build/)

If your document root is named www, httpdocs or wwwroot, rename the
public_html folder to that name. No code changes are required.

Then:
1. Make next-respack/storage, bootstrap/cache and database writable (775).
2. Create a MySQL/MariaDB database in your hosting panel.
3. Open your domain in a browser and follow /install.

Full guide: next-respack/docs/INSTALL-SHARED-HOSTING.md
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
