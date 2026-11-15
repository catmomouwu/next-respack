# VPS 部署（Nginx / Apache）

适用于拥有 SSH 与 root/sudo 的独立服务器或云主机。发行包 `next-respack-vps.zip` 已包含 `vendor/` 与构建资源。

## 1. 系统依赖

```bash
# Debian / Ubuntu
sudo apt install nginx php8.3-fpm php8.3-cli php8.3-mbstring php8.3-xml \
  php8.3-curl php8.3-zip php8.3-mysql php8.3-sqlite3 php8.3-fileinfo \
  php8.3-openssl php8.3-dom mysql-server unzip
```

Node.js 仅在开发时需要。

## 2. 部署代码

```bash
sudo mkdir -p /var/www/next-respack
sudo unzip next-respack-vps.zip -d /tmp/next-respack
sudo rsync -a /tmp/next-respack/next-respack/ /var/www/next-respack/
cd /var/www/next-respack

sudo chown -R www-data:www-data storage bootstrap/cache
sudo chmod -R 775 storage bootstrap/cache
cp .env.example .env   # 可选：安装向导也会自动创建/改写
```

## 3. 数据库

```sql
CREATE DATABASE next_respack CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'next_respack'@'localhost' IDENTIFIED BY '强密码';
GRANT ALL PRIVILEGES ON next_respack.* TO 'next_respack'@'localhost';
FLUSH PRIVILEGES;
```

## 4. Web 服务器

### Nginx

使用 [deploy/nginx/next-respack.conf](../deploy/nginx/next-respack.conf)：

```bash
sudo cp deploy/nginx/next-respack.conf /etc/nginx/sites-available/next-respack.conf
sudo ln -s /etc/nginx/sites-available/next-respack.conf /etc/nginx/sites-enabled/
sudo nginx -t && sudo systemctl reload nginx
```

需要修改 `server_name`、`root`、PHP-FPM socket 路径与 `alias` 存储路径。若启用 X-Accel 下载模式，`/_protected/respack/` 的 `alias` 必须指向后台设置的本地存储根目录。

### Apache

```bash
sudo a2enmod rewrite headers expires
sudo cp deploy/apache/next-respack.conf /etc/apache2/sites-available/next-respack.conf
sudo a2ensite next-respack
sudo systemctl reload apache2
```

Apache 下请保持 **PHP 流式下载**；`X-Accel-Redirect` 仅适用于 Nginx。

## 5. HTTPS 与安装

```bash
sudo certbot --nginx -d respack.example.com
```

安装前请确认 `.env` 中的数据库连接（`DB_CONNECTION` / `DB_HOST` / `DB_PORT` / `DB_DATABASE` / `DB_USERNAME` / `DB_PASSWORD`）可用。`nr:install` 会先检测连接，连接失败会给出明确错误并以非零退出码结束，不会修改数据。

### 5.1 CLI 安装（推荐用于自动化）

安装过程会改写 `.env`（写入 `APP_KEY`、`APP_INSTALLED` 与数据库配置），因此先确保 `.env` 存在且可被 Web 用户写入，并已填写数据库连接：

```bash
cd /var/www/next-respack

# 若尚未创建 .env，先复制并填写数据库配置
sudo cp -n .env.example .env
sudoedit .env

# 安装过程会写入 .env
sudo chown www-data:www-data .env
```

```bash
sudo -u www-data php artisan nr:install \
  --site-name="Next Respack" \
  --site-url="https://respack.example.com" \
  --admin-name="owner" \
  --admin-email="owner@example.com"
```

命令会提示输入管理员密码（输入不回显，密码不会写入日志或输出到终端）。如需完全非交互（例如配置管理工具），可追加 `--admin-password="至少10位的强密码"`；注意该参数会留在 Shell 历史与进程列表中，执行后请清理历史，或优先使用交互输入。

安装内容包括：数据库迁移（`migrate --force`）、默认系统设置、内置 Pack Format 数据、首位超级管理员、用户配额、本地存储目录、安装记录与安装锁。安装完成后 `/install` 自动失效。

已安装时再次执行会被拒绝且不修改任何数据。如需重新初始化（保留现有数据）：

```bash
sudo -u www-data php artisan nr:install --force \
  --site-name="Next Respack" \
  --site-url="https://respack.example.com" \
  --admin-name="owner" \
  --admin-email="owner@example.com"
```

`--force` 不会删除资源包、用户或设置；同一邮箱的管理员会被更新，安装记录、系统设置与配额不会重复创建。

### 5.2 浏览器安装

浏览器访问 `https://respack.example.com/install` 完成向导。站点 URL 必须为 HTTPS。CLI 安装与浏览器安装择一即可。

## 6. 权限收尾

```bash
sudo chmod 600 .env
sudo chown www-data:www-data .env
sudo find storage -type d -exec chmod 775 {} \;
```

确认 `/var/www/next-respack` 不在 Nginx/Apache 文档根内（文档根应为 `public/`）。

## 7. 可选运维

### 队列

默认 `QUEUE_CONNECTION=sync`，无需 Worker。若改为数据库队列：

```bash
sudo -u www-data php artisan queue:work --sleep=3 --tries=3
```

配合 Supervisor 常驻。

### 定时任务（Scheduler / Cron）

应用内置调度任务：游客上传清理 `guests:purge` 每小时执行，Pack Format 同步 `pack-formats:sync` 每天执行。生产环境建议添加系统 Cron，每分钟触发 Laravel 调度器：

```cron
* * * * * cd /var/www/next-respack && php artisan schedule:run >> /dev/null 2>&1
```

示例见 [deploy/cron.txt](../deploy/cron.txt)。未配置 Cron 时，可手动运行调度器或仅同步 Pack Format：

```bash
sudo -u www-data php artisan schedule:run
sudo -u www-data php artisan nr:update --sync-formats
```

### 更新

```bash
cd /var/www/next-respack

# 1. 备份数据库、.env 与 storage（见 BACKUP-RESTORE.md）
sudo ./deploy/backup.sh

# 2. 替换代码，保留 .env 与 storage
sudo rsync -a --delete --exclude .env --exclude storage/ /tmp/next-respack/ /var/www/next-respack/

# 3. 执行迁移并清理 config / route / view 缓存
sudo -u www-data php artisan nr:update

# 可选：同时同步 Pack Format 数据（需要访问 minecraft.wiki）
sudo -u www-data php artisan nr:update --sync-formats
```

`nr:update` 在未安装时会拒绝执行；迁移或缓存清理失败会以非零退出码结束。更新只执行增量迁移，不会清空现有数据。

如需构建生产缓存，在 `nr:update` 之后执行：

```bash
sudo -u www-data php artisan config:cache
sudo -u www-data php artisan route:cache
sudo -u www-data php artisan view:cache
```

### 性能

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

修改 `.env` 或后台设置后需重新执行 `php artisan config:clear`（后台保存设置会自动清理缓存）。

## 8. 日志与监控

- 应用日志：`storage/logs/laravel.log`
- Web 服务器日志：`/var/log/nginx/` 或 `/var/log/apache2/`
- 下载统计：后台“审计日志”与版本页
