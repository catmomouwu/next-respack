# 配置说明

## 配置文件

| 文件 | 用途 |
| --- | --- |
| `.env` | 环境变量：应用密钥、数据库、会话、缓存、邮件；安装向导自动生成 |
| `config/nextrespack.php` | 产品参数：安装路径、存储根、Pack Format 数据源、上传限制 |
| 后台 → 设置 | 站点名称/URL、注册与邮箱验证、配额、下载模式、SMTP、Minecraft 数据同步 |

后台设置的修改会写入 `system_settings` 表并即时生效，无需改 `.env`。

## 数据库

安装向导支持：

- **MySQL / MariaDB**：填写主机、端口、库名、用户名、密码，测试连接与建表权限。
- **SQLite**：填写数据库文件路径；适合单机小规模部署。需 `pdo_sqlite` 扩展。

安装完成后 `.env` 中的 `DB_*` 即实际连接。切换数据库需重新安装或迁移数据。

## 存储

| 驱动 | 说明 |
| --- | --- |
| `local` | 默认。目录默认 `storage/app/private/resource-packs`，必须位于文档根之外。安装时写入 `.htaccess` 与 `index.html` 防止误访问。 |
| `s3` | S3 兼容对象存储（AWS S3、MinIO、Cloudflare R2 等）。支持自定义 Endpoint、Region、Bucket、Path-style、前缀。Secret Key 使用 Laravel `Crypt` 加密存储。 |

后台 **存储管理** 支持：

- 测试当前/目标后端读写。
- 创建迁移计划：从当前后端复制到目标后端，逐文件校验大小与 SHA-1，成功后切换设置。
- 清理本地孤儿文件（仅本地存储）。

迁移不会删除源文件；确认无误后可手动清理。

## 上传与配额

| 设置 | 默认 | 说明 |
| --- | --- | --- |
| 最大上传 | 512 MB | 同时受 PHP `upload_max_filesize` / `post_max_size` 限制 |
| 默认配额 | 2048 MB | 管理员新用户配额 |
| 新用户配额 | 1024 MB | 注册用户初始配额 |
| ZIP 条目上限 | 10000 | `config/nextrespack.php` |
| 解压后总大小 | 2 GB | 防 ZIP 炸弹 |
| 最大压缩比 | 1000 | 防高压缩比炸弹 |

配额按用户全部版本的 `file_size` 汇总，上传与删除在数据库事务内加锁结算。

## 下载模式

- `php`：由 PHP 流式输出，适用于共享主机与 Apache。
- `x-accel`：Nginx 内部重定向，`/_protected/respack/` 的 `alias` 指向本地存储根；仅本地存储可用。参见 [DEPLOY-VPS.md](DEPLOY-VPS.md)。

## 邮件

后台设置 SMTP 后，注册验证、找回密码等邮件即时生效。未配置时 `MAIL_MAILER=log`，邮件写入 `storage/logs/laravel.log`，适合测试。

## 国际化

- 默认语言 `zh_CN`，可切换 English。
- 翻译文件：`lang/zh_CN.json`、`lang/zh_CN/*.php`。
- 新增文案请在 `lang/zh_CN.json` 中补充中文键值，英文原文作为键。

## 安全相关环境变量

```ini
APP_ENV=production
APP_DEBUG=false
SESSION_SECURE_COOKIE=true   # HTTPS 站点
```

生产环境严禁 `APP_DEBUG=true`。
