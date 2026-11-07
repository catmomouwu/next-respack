# 升级指南

## 升级前

1. 完整备份数据库、`storage/` 与 `.env`（见 [BACKUP-RESTORE.md](BACKUP-RESTORE.md)）。
2. 记录当前版本：后台仪表盘或 `config/nextrespack.php` 的 `version`。
3. 进入维护模式：`php artisan down`（共享主机可在后台关闭站点或使用主机维护模式）。
4. 确认新版本要求的 PHP 版本与扩展。

## 升级步骤（VPS）

```bash
cd /var/www/next-respack

# 1. 备份
sudo ./deploy/backup.sh

# 2. 替换代码，保留 .env 与 storage
sudo rsync -a --delete --exclude .env --exclude storage/ /tmp/next-respack/ /var/www/next-respack/

# 3. 安装依赖（发行包已含 vendor，可跳过）
composer install --no-dev --optimize-autoloader

# 4. 执行数据库迁移
php artisan migrate --force

# 5. 重建缓存
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache

# 6. 恢复权限
sudo chown -R www-data:www-data storage bootstrap/cache
sudo chmod -R 775 storage bootstrap/cache

php artisan up
```

## 升级步骤（共享主机）

1. 通过文件管理器上传新发行包中的 `next-respack/`，**保留**现有 `.env` 与 `storage/`。
2. 覆盖 `public_html/index.php`、`.htaccess` 与 `build/`。
3. 浏览器访问 `/install` 确认仍为 404（已安装）。
4. 若新版本包含迁移，访问站点后由后台提示或临时执行安装器页面不会触发迁移；共享主机无 SSH 时请联系主机商提供一次性 PHP 执行环境，或使用主机面板的“运行 PHP 脚本”功能执行：
   ```php
   <?php require '/home/user/next-respack/vendor/autoload.php';
   $app = require '/home/user/next-respack/bootstrap/app.php';
   $kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
   $kernel->call('migrate', ['--force' => true]);
   echo $kernel->output();
   ```
   执行后立即删除该临时脚本。

## 回滚

- 恢复旧代码目录。
- 恢复升级前数据库备份（若新版本已执行不可逆迁移）。
- 恢复 `storage/` 与 `.env`。
- 清理缓存后退出维护模式。

## 兼容性说明

- 数据库迁移均为增量；正常情况下不需要重建数据库。
- 若从早期版本升级且缺少 `installations` 表记录，产品仍以 `storage/app/installed.lock` 判定已安装。
- 升级后如出现 500，先查看 `storage/logs/laravel.log`，并确认 `storage/` 可写。
