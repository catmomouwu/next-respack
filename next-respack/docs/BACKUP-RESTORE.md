# 备份与恢复

需要备份的内容：

1. **数据库**（用户、资源包元数据、版本、下载日志、设置、Pack Format）。
2. **私有存储** `storage/app/private/resource-packs/`（本地存储时）。
3. **`.env`**（包含 `APP_KEY`，恢复时必不可少）。
4. 可选：`storage/logs/`、`storage/app/installed.lock`、`storage/app/install-state.json`。

> `.env` 中的 `APP_KEY` 用于解密 S3 / SMTP 密钥。丢失 `APP_KEY` 将无法解密后台保存的 Secret，需要重新填写。

## 手动备份

### MySQL / MariaDB

```bash
mysqldump --single-transaction --routines --triggers \
  -u next_respack -p next_respack > next_respack-$(date +%F).sql
```

### SQLite

```bash
sqlite3 database/database.sqlite ".backup 'next_respack-$(date +%F).sqlite'"
```

### 文件

```bash
tar -czf next-respack-files-$(date +%F).tar.gz \
  storage/app/private/resource-packs .env
```

### 一键脚本

使用 [deploy/backup.sh](../deploy/backup.sh)，修改顶部变量后执行：

```bash
sudo ./deploy/backup.sh
```

输出目录包含 SQL 转储、资源包归档、`.env` 与校验和。

## 恢复

1. 停止写入（进入维护模式）：`php artisan down`。
2. 恢复代码版本（与备份时一致）。
3. 恢复 `.env`，确认 `APP_KEY` 一致。
4. 导入数据库：
   ```bash
   mysql -u next_respack -p next_respack < next_respack-2026-01-01.sql
   ```
5. 解压资源包目录到 `storage/app/private/resource-packs/`。
6. 修正权限：`chmod -R 775 storage bootstrap/cache`。
7. 清理缓存：
   ```bash
   php artisan config:clear
   php artisan cache:clear
   php artisan view:clear
   ```
8. 退出维护模式：`php artisan up`。
9. 验证：登录后台、随机下载 2–3 个资源包并核对 SHA-1。

## 恢复后校验

- `installed.lock` 是否存在；访问 `/install` 应返回 404。
- 后台存储页显示文件数与字节数。
- 下载链接可正常返回 200 / 206。
- Pack Format 公开页有数据；必要时后台手动同步。

## 建议策略

- 每日数据库 + 文件增量备份，保留 14–30 天。
- 备份文件与站点放在不同磁盘 / 对象存储。
- 定期做一次完整恢复演练。
