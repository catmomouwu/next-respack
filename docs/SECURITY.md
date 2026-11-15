# 安全说明

## 部署基线

- 使用 HTTPS，站点 URL 必须为 `https://`；启用 HSTS 与安全 Cookie。
- 生产环境 `APP_ENV=production`、`APP_DEBUG=false`。
- 应用目录、`storage/`、`vendor/`、`database/` 不得位于 Web 文档根目录。
- `.env` 权限 `600`，安装完成后删除安装临时文件与备份。
- 共享主机确认 `public_html` 只包含 `index.php`、`.htaccess` 与 `build/` 静态资源。

## 权限模型

| 角色 | 能力 |
| --- | --- |
| Guest | 浏览公开页、Pack Format 查询、匿名下载已启用版本 |
| User | 上传/管理自己的资源包、账户设置 |
| Admin | 用户、资源包、设置、审计日志、Pack Format 维护 |
| Super Admin | 以上全部 + 存储迁移、孤儿清理 |

所有后台路由均经过 `admin` / `superadmin` 中间件；资源包操作校验所有权。管理员不能停用自己的账户；不能删除或降级最后一位超级管理员。

## 上传安全

- 扩展名、MIME、ZIP 文件签名、CRC 一致性校验。
- 拒绝 Zip Slip（`../`、绝对路径）、符号链接、加密条目。
- 限制条目数量、解压后总大小与压缩比，防 ZIP 炸弹。
- 强制根目录 `pack.mcmeta`，解析 JSON 与 pack_format / min_format / max_format / supported_formats。
- 存储文件名使用 UUIDv7，避免路径注入与重名覆盖。
- 最终存储字节计算 SHA-1，下载时以此作为 ETag 与完整性依据。

## 下载链接

- Token 为 `base64url(HMAC-SHA256(APP_KEY, "download|public_id|generation"))`。
- 数据库只保存 Token 的 SHA-256 摘要，数据库泄露无法还原链接。
- 重置 Token 会提升 generation，旧链接立即失效。
- 支持停用版本、停用资源包；下载日志只保存加盐 IP 哈希（可关闭）。

## 安装器

- 安装完成后写入 `storage/app/installed.lock`、`APP_INSTALLED=true` 与 `installations` 记录，`/install` 返回 404。
- 安装步骤有会话校验、限流与文件锁，防并发重复安装。
- 确认页对邮箱、密码、S3 Secret 等敏感信息脱敏。
- 安装失败记录状态但不写完成锁，支持重试。

## 密钥管理

- `APP_KEY` 是加密 Cookie、S3 Secret、SMTP 密码与下载 Token 的基础，必须备份且不可泄露。
- 更换 `APP_KEY` 会使已有下载链接、加密设置失效；如需轮换，请先导出并重新填写后台密钥，再批量重置下载 Token。

## 审计与日志

- 登录、注册、邮箱验证、资源包操作、管理员操作、存储迁移、清理、设置修改均写入 `audit_logs`。
- 下载记录写入 `download_logs`，后台可分页查看。
- 日志中不记录密码、Token 明文或 Secret。

## 加固建议

- 后台路径可按需通过 Nginx/Apache 限制 IP 或加额外认证层。
- 限制 `client_max_body_size` 与 PHP 上传上限；按需调整上传配额。
- 定期更新 PHP、Laravel 依赖与系统补丁：
  ```bash
  composer update --no-dev
  ```
- 使用 WAF / 主机防火墙，限制异常高频下载与登录尝试。
- 定期审计 `audit_logs` 中的角色变更与删除操作。

## 漏洞报告

发现安全问题请勿公开提交 Issue，请通过仓库安全渠道私下联系维护者，并附复现步骤与影响范围。
