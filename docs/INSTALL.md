# 安装指南

Next Respack 支持三种安装路径：

1. 共享主机（虚拟主机 / cPanel / 宝塔）——无需 SSH、Composer、Node。
2. VPS / 独立服务器（Nginx 或 Apache）。
3. 本地开发（`php artisan serve`）。

无论哪种方式，安装完成后都通过浏览器访问 `/install` 完成站点初始化。

## 0. 安装前检查

- PHP 8.3+，扩展：ctype、curl、dom、fileinfo、filter、hash、json、mbstring、openssl、pcre、pdo、pdo_mysql、session、tokenizer、xml、zip。
- 数据库：MySQL 5.7+ / MariaDB 10.3+ / SQLite 3。
- 准备 HTTPS 证书（Minecraft 客户端只应通过 HTTPS 下载）。
- `storage/`、`bootstrap/cache/`、`database/` 可写；项目根目录在安装期间可写（用于生成 `.env`）。
- `upload_max_filesize` 与 `post_max_size` 建议 ≥ 512 MB。共享主机可在控制面板修改 PHP 配置。

## 1. 安装向导

访问站点域名根路径，会自动跳转到 `/install`。

| 步骤 | 内容 |
| --- | --- |
| 欢迎 | 产品介绍与开始安装 |
| 环境检测 | PHP 版本、扩展、目录写权限、上传限制、HTTPS、目录安全性；必需项失败时无法继续 |
| 数据库 | 选择 MySQL / MariaDB / SQLite；可先“测试连接”，通过后才能保存 |
| 存储 | 本地目录或 S3 兼容对象存储；可测试读写与删除 |
| 站点 | 站点名称、描述、公开 URL（非 localhost 必须 HTTPS）、默认语言、时区、上传上限、默认配额、注册开关 |
| 管理员 | 首位超级管理员用户名、邮箱、密码（≥10 位，需二次确认） |
| 确认 | 汇总安装信息，邮箱、密码、密钥等敏感字段脱敏显示 |
| 执行 | 写入 `.env`、执行迁移、创建管理员、写入站点与存储设置、导入内置 Pack Format 数据、写入安装锁 |
| 完成 | 显示管理员账号与站点地址；失败时显示失败步骤与可重试按钮 |

安装过程要点：

- 安装期间使用临时密钥维持会话；正式 `APP_KEY` 在安装时生成并写入 `.env`。
- 安装失败不会写入完成锁；刷新或重新提交可继续/重试，已完成的步骤保持幂等。
- 成功后 `/install` 返回 404，`APP_INSTALLED=true` 且 `storage/app/installed.lock` 存在。
- 安装成功后请立即删除或禁止访问安装日志中的敏感信息；`.env` 权限建议 `600`。

## 2. 安装后的首个操作

1. 使用安装时创建的超级管理员登录。
2. 进入 **后台 → 设置** 配置 SMTP、下载模式、是否允许注册、自动同步 Pack Format（默认关闭）。
3. 进入 **后台 → 存储** 检查存储状态；如使用 S3，确认凭据已加密保存。
4. 上传一个测试资源包，确认下载链接、SHA-1 与 Range 请求正常。
5. 配置备份与 HTTPS 强制跳转。

## 3. 安装失败恢复

- 页面会显示失败步骤。修正问题后回到 `/install`，向导会保留已保存的步骤状态。
- 若数据库已部分迁移：删除目标数据库中的表（或使用新库）后重试；也可保留表重试，迁移是幂等的。
- 若 `.env` 写入失败：检查项目根目录写权限。安装器会保留 `.env.backup-YYYYMMDDHHMMSS`。
- 若安装中途浏览器关闭：重新访问 `/install`，状态文件 `storage/app/install-state.json` 会记录当前步骤。
- 彻底重装：删除 `storage/app/installed.lock`、`storage/app/install-state.json`、`storage/app/.installer-key`，清空数据库，并将 `.env` 中 `APP_INSTALLED` 改为 `false`。

## 4. 目录与权限

```bash
chmod -R 775 storage bootstrap/cache
chmod 600 .env
```

Web 服务器用户需对 `storage/`、`bootstrap/cache/`、`database/`（SQLite）可写。资源包默认存储于 `storage/app/private/resource-packs/`，必须位于文档根目录之外。
