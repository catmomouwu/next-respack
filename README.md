# Next Respack

> 自托管的 Minecraft Java 版资源包托管平台。上传、校验、版本管理、匿名下载链接与 Minecraft 版本数据一站完成。

Next Respack 基于 **Laravel 13 / PHP 8.3** 构建，内置 Web 安装向导，可直接部署在**共享主机**（无需 SSH、Composer、Node、Cron、Redis、Supervisor、Docker），也提供 VPS（Nginx / Apache）配置。应用代码与私有存储始终位于 Web 根目录之外。

## 功能

- **Web 安装向导**：欢迎页 → 环境检测 → 数据库（MySQL / MariaDB / SQLite）连接与权限测试 → 本地 / S3 存储选择与测试 → 站点信息 → 首位超级管理员 → 确认摘要（敏感信息脱敏）→ 执行安装 → 失败重试与断点状态。
- **账户体系**：用户名或邮箱登录、注册、邮箱验证、找回密码、账户资料与密码修改；角色 Guest / User / Admin / Super Admin；后端权限校验、账户启用状态与配额。
- **资源包 ZIP 上传与校验**：扩展名 / MIME / 文件签名 / CRC 一致性、Zip Slip、符号链接、加密条目、ZIP 炸弹与压缩比、条目数量、根目录 `pack.mcmeta`、JSON 与格式字段；状态 `ZIP_INVALID`、`PACK_INVALID`、`METADATA_INVALID`、`FORMAT_UNKNOWN`、`VALID`、`VALID_WITH_WARNING`。
- **完整性与版本**：以 UUIDv7 命名存储文件，同一次上传的最终存储字节计算 SHA-1；同一资源包支持多版本、启用 / 停用、删除与配额结算（数据库事务加锁）。
- **Minecraft Pack Format 数据模块**：从 minecraft.wiki 采集、完整版本表（正式版 / 快照 / 预发布 / RC）、整数与小数格式、主 / 次版本与区间字段；同步日志与历史快照、手动同步、差异比对、失败保护、人工修正、JSON / CSV 导入、回滚、公开查询页与 REST API。
- **匿名下载链接**：高熵 HMAC Token，数据库仅保存 SHA-256 摘要；GET / HEAD、HTTP Range（206 / 416）、ETag / 304；下载日志与统计；Token 重置 / 停止；`server.properties` 生成器。
- **游客上传**：无需注册即可上传资源包，自建 SVG 验证码与频率限制；单文件上限 200 MB，7 天后自动删除（注册后永久保存），过期文件在访问上传页时自动清理，也支持 `php artisan guests:purge` 定时清理。
- **控制台与后台**：用户仪表盘、资源包管理、管理员用户 / 资源包 / 设置 / 审计日志、存储迁移与孤儿文件清理、Pack Format 数据维护。
- **界面与国际化**：中文 / English、现代响应式布局、深色模式。
- **测试与发布**：PHPUnit 功能 / 单元测试；共享主机与 VPS 双发行包（含 `vendor`、已构建前端资源、站点配置、文档与校验和）。

## 运行环境

| 项目 | 要求 |
| --- | --- |
| PHP | 8.3 及以上 |
| PHP 扩展 | ctype、curl、dom、fileinfo、filter、hash、json、mbstring、openssl、pcre、pdo、pdo_mysql、session、tokenizer、xml、zip（SQLite 可选 `pdo_sqlite`） |
| 数据库 | MySQL 5.7+ / MariaDB 10.3+ / SQLite 3 |
| Web 服务器 | Apache 2.4 / Nginx 1.20+ / 共享主机（无需 SSH） |
| 前端构建 | 仅开发时需要 Node.js 20+；发行包已包含构建产物 |
| 可选 | S3 兼容对象存储；邮件 SMTP；Cron（可选：游客上传过期清理等维护任务，非必需） |

## 快速开始（开发）

```bash
composer install
cp .env.example .env
php artisan key:generate
npm install
npm run build
php artisan serve
```

打开 `http://127.0.0.1:8000`，跟随 `/install` 向导完成安装。开发环境也可直接使用 SQLite。

运行测试：

```bash
php artisan test
```

## 生产部署

- 共享主机：[docs/INSTALL-SHARED-HOSTING.md](docs/INSTALL-SHARED-HOSTING.md)
- VPS（Nginx / Apache）：[docs/DEPLOY-VPS.md](docs/DEPLOY-VPS.md)
- 配置说明：[docs/CONFIGURATION.md](docs/CONFIGURATION.md)
- 升级：[docs/UPGRADE.md](docs/UPGRADE.md)
- 备份与恢复：[docs/BACKUP-RESTORE.md](docs/BACKUP-RESTORE.md)
- 安全加固：[docs/SECURITY.md](docs/SECURITY.md)
- 故障排查：[docs/TROUBLESHOOTING.md](docs/TROUBLESHOOTING.md)
- Pack Format 同步：[docs/PACK-FORMAT-SYNC.md](docs/PACK-FORMAT-SYNC.md)
- 下载与 API：[docs/API.md](docs/API.md)

## 目录结构

```text
next-respack/
├── app/                    应用代码（控制器、服务、模型、中间件）
├── bootstrap/              Laravel 启动与缓存
├── config/                 应用配置（nextrespack.php 为产品配置）
├── database/               迁移、工厂、内置 Pack Format 数据
├── deploy/                 共享主机包装、Nginx / Apache / PHP 配置、备份脚本
├── docs/                   安装、部署、运维与安全文档
├── lang/                   中文翻译与校验消息
├── public/                 Web 入口（生产环境文档根目录）
├── resources/              视图、CSS、JS
├── routes/                 Web 路由
├── storage/                日志、会话、缓存与私有资源包
├── tests/                  PHPUnit 测试
└── vendor/                 Composer 依赖（发行包已包含）
```

## 安全要点

- `.env`、`storage/`、`vendor/`、`database/` 均不得暴露在 Web 根目录。
- 下载 Token 只以 SHA-256 摘要入库，泄露数据库也无法还原下载链接。
- 安装完成后 `/install` 自动失效并写入锁文件；失败时可通过安装状态页重试。
- 详见 [docs/SECURITY.md](docs/SECURITY.md)。

## 许可

MIT，见 [LICENSE](LICENSE)。
