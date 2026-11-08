# Changelog

## 1.0.2 — 2026-11-08

### 新增

- WebDAV 存储后端：安装向导与管理后台均可选择，支持 Nextcloud / ownCloud / Synology 等服务器，含连接测试、路径前缀与 TLS 校验；存储迁移支持迁移到 WebDAV。

### 改进

- 数据库最低要求降至 MySQL 5.7 / MariaDB 10.3（原为 MySQL 8.0 / MariaDB 10.6）。
- 安装向导移除仅 MySQL 8 支持的 `utf8mb4_0900_ai_ci` 排序规则选项。

### 修复

- 修复 `minecraft_pack_format_versions` 索引名超过 MySQL 64 字符上限导致安装失败的问题。

## 1.0.1 — 2026-11-08

### 改进

- 资源包版本详情页的“检测到的版本”长列表改为“建议使用的版本”，自动从匹配版本中选出最新正式版（无正式版时回退到最新快照/预发布版）。

## 1.0.0 — 2026-11-06

首个正式版本。

### 安装

- Web 安装向导：环境检测、数据库与存储测试、站点配置、首位超级管理员、确认摘要脱敏、执行、失败重试与状态恢复。
- 安装锁、`APP_INSTALLED` 标记与 `installations` 记录三重判定；`/install` 安装后失效。
- 共享主机与 VPS 双发行包，含 `vendor`、构建资源、服务器配置与文档。

### 账户与权限

- 用户名 / 邮箱登录、注册、邮箱验证、找回密码、账户资料与密码修改。
- Guest / User / Admin / Super Admin 四级角色；后端权限与账户启用状态校验。
- 用户配额、管理员用户管理、审计日志。

### 资源包

- ZIP 上传校验：扩展名、MIME、签名、CRC、Zip Slip、符号链接、加密、ZIP 炸弹、压缩比、条目数量、根 `pack.mcmeta`、JSON 与格式字段。
- UUIDv7 存储文件名，最终字节 SHA-1，多版本、启停、删除与配额事务结算。
- 本地 / S3 存储适配器，存储测试、迁移计划与孤儿文件清理。

### Minecraft Pack Format

- minecraft.wiki 采集与解析，内置 830 条版本快照。
- 同步日志、历史快照、差异、失败保护、人工修正、JSON / CSV 导入、回滚。
- 公开查询页与 REST API。

### 下载

- HMAC 匿名 Token（数据库仅存 SHA-256 摘要）。
- GET / HEAD、Range 206 / 416、ETag / 304、下载日志与统计、Token 重置与停用。
- `server.properties` 生成器，Nginx X-Accel 可选模式。

### 界面

- 中文 / English、响应式布局、深色模式、仪表盘与后台管理界面。

### 质量

- PHPUnit 功能与单元测试：安装器、认证、上传校验、下载协议、Pack Format、权限控制。
