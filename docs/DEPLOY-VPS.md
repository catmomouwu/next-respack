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

浏览器访问 `https://respack.example.com/install` 完成向导。站点 URL 必须为 HTTPS。

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

### 定时任务

产品默认不要求 Cron。游客上传的过期文件会在访问上传页时自动清理，也可手动执行 `php artisan guests:purge`。若要严格按小时清理，可添加：

```cron
* * * * * cd /var/www/next-respack && php artisan schedule:run >> /dev/null 2>&1
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
