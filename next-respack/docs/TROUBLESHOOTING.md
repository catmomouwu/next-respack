# 故障排查

## 安装阶段

| 现象 | 原因 / 处理 |
| --- | --- |
| 一直跳回环境检测 | 必需项失败。查看红色项并按提示修复；确认 `storage/`、`bootstrap/cache/`、`database/` 可写 |
| 数据库测试失败 | 主机/端口/库名/用户错误，或用户无建表权限；SQLite 路径目录不可写 |
| 存储测试失败 | 本地目录不可写或位于 `public/` 内；S3 Endpoint/Bucket/凭据错误 |
| 保存站点信息报 HTTPS | 非 localhost 必须使用 `https://` URL |
| 执行安装 500 | 查看 `storage/logs/laravel.log`；常见为 `.env` 不可写、数据库权限、磁盘空间不足 |
| `/install` 404 | 已安装。需重装时见 [INSTALL.md](INSTALL.md) 的“彻底重装” |
| 安装后立即 500 | 清理 `bootstrap/cache/*.php`；确认 `storage/framework/{cache,sessions,views}` 存在且可写 |

## 上传阶段

| 现象 | 原因 / 处理 |
| --- | --- |
| 页面提示文件过大 | PHP `upload_max_filesize` / `post_max_size` / Web 服务器限制；同时检查后台最大上传设置 |
| `ZIP_INVALID` | 文件损坏、非 ZIP、加密条目、Zip Slip、符号链接、条目过多或压缩比异常 |
| `PACK_INVALID` | 根目录缺少 `pack.mcmeta`；注意不能放在子目录 |
| `METADATA_INVALID` | JSON 语法错误、缺少 `pack_format` 或 `description` |
| `FORMAT_UNKNOWN` | 版本表中的 pack_format 不存在；可正常保存但显示警告，管理员可导入新数据 |
| 配额不足 | 后台调整用户配额；或删除旧版本释放空间 |
| 重复上传被拒 | 同用户同名/同内容校验重复；勾选“允许重复”或使用新版本 |

## 下载阶段

| 现象 | 原因 / 处理 |
| --- | --- |
| 链接 404 | Token 已重置 / 版本存在但文件丢失 / 链接错误 |
| 链接 403 | 版本或资源包已停用；账户被禁用（后者与公开下载无关） |
| 206 正常但断流 | 主机 `output_buffering`、代理缓冲或 PHP `max_execution_time`；建议 `set_time_limit` 已自动处理 |
| X-Accel 模式 404 | Nginx `internal` location 与 `alias` 路径不匹配；或当前为 S3 存储 |
| 下载没有计数 | 使用 HEAD 请求不计数；GET 才计数 |

## 页面 / 资源

| 现象 | 原因 / 处理 |
| --- | --- |
| 无样式、控制台 404 | `public/build/manifest.json` 与资源缺失；重新运行 `npm run build` 或从发行包复制 |
| 中文乱码 | 确认 `APP_LOCALE=zh_CN`，数据库 `utf8mb4` |
| 时区错误 | 后台设置时区；或检查 PHP `date.timezone` |
| 邮件未发送 | 后台 SMTP 配置错误；测试期可将 `MAIL_MAILER=log` 查看日志 |

## Pack Format

| 现象 | 原因 / 处理 |
| --- | --- |
| 同步失败 | minecraft.wiki 不可访问、页面结构变化、返回数据不足 30 条；旧数据会保留 |
| 公开页无数据 | 安装时内置数据导入失败；后台手动同步或导入 JSON/CSV |
| 导入被拒绝 | JSON/CSV 格式错误或少于 30 条；检查表头与字段 |

## 性能

- 大量下载时优先使用 Nginx `x-accel`（仅本地存储）。
- 上传与下载均为流式处理，不将整个 ZIP 载入内存。
- 定期清理孤儿文件与旧日志；必要时归档 `download_logs`。

## 获取帮助

请附上：

- 版本号（`config/nextrespack.php` 的 `version`）
- PHP / 数据库版本
- 部署方式（共享主机 / VPS / 本地）
- `storage/logs/laravel.log` 相关片段（注意脱敏）
- 复现步骤与浏览器截图
