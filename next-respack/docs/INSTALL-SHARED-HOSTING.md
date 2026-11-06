# 共享主机部署

本方案面向 cPanel、宝塔、DirectAdmin、Plesk 等常见共享主机：**无需 SSH、Composer、Node、Cron、Redis、Supervisor、Docker**。发行包 `next-respack-shared-hosting.zip` 已包含 `vendor/` 与已构建的前端资源。

## 推荐目录结构

```text
/home/your-user/
├── next-respack/              # 完整应用（Web 不可访问）
│   ├── app/  bootstrap/  config/  database/
│   ├── lang/  resources/  routes/  storage/
│   ├── vendor/  .env  artisan
│   └── public/                # 仅作为源，不直接对外
└── public_html/               # 站点文档根目录
    ├── index.php              # 包装器（来自 deploy/shared-hosting/public_html）
    ├── .htaccess
    └── build/                 # 从 next-respack/public/build 复制
```

`public_html` 中**只允许**入口文件与静态资源；其余文件全部在 `next-respack/` 内。

## 步骤

### 1. 上传文件

1. 将 `next-respack-shared-hosting.zip` 中的 `next-respack/` 上传到用户主目录（或任意 Web 根目录之外的路径）。
2. 将 `public_html/` 中的 `index.php`、`.htaccess` 上传到站点文档根目录。
3. 将 `next-respack/public/build/` 整个目录复制到 `public_html/build/`。
4. 若主机文档根不是 `public_html`，请把包装器放到实际文档根。

> 需要修改 `index.php` 中 `$appPath = __DIR__.'/../next-respack';` 为你实际的绝对/相对路径。目录深度不同时请相应调整 `../` 层级。

### 2. 权限

在文件管理器中设置：

- `next-respack/storage/`、`next-respack/bootstrap/cache/`、`next-respack/database/`：775（可写）。
- `next-respack/.env`：600（安装完成后）。
- 安装期间 `next-respack/` 根目录需可写，以便生成 `.env`。

### 3. PHP 配置

在主机控制面板的 PHP 设置中调整：

```ini
upload_max_filesize = 512M
post_max_size = 520M
memory_limit = 256M
max_execution_time = 300
max_input_time = 300
```

确认 PHP 版本为 8.3+，并启用 `zip`、`pdo_mysql`、`mbstring`、`openssl`、`fileinfo`、`curl`。

### 4. 数据库

在控制面板创建数据库与用户，记录主机、端口、库名、用户名、密码。安装向导会测试连接与建表权限。

### 5. 安装

访问站点根域名，按 [INSTALL.md](INSTALL.md) 完成向导。安装器会自动写入 `.env`，无需手动配置。

### 6. HTTPS

在主机面板启用 Let's Encrypt / 自带证书，并开启强制 HTTPS。安装时填写的站点 URL 必须是 `https://`。

### 7. 收尾

- 删除 `public_html` 下多余示例文件，保留 `index.php`、`.htaccess`、`build/`。
- 确认 `next-respack/storage/app/installed.lock` 已生成，`/install` 返回 404。
- 设置主机每日自动备份（数据库 + `next-respack/storage/` + `.env`）。

## 常见问题

| 问题 | 处理 |
| --- | --- |
| 404 / 500 | 检查文档根指向、`index.php` 路径、`.htaccess` 是否启用 `mod_rewrite` |
| 页面无样式 | `public_html/build/manifest.json` 与 `build/assets/` 是否完整 |
| 安装器无法写 `.env` | 项目根目录权限；或先手动创建空 `.env` 并设 600 |
| 上传大文件失败 | PHP 的 `upload_max_filesize`、`post_max_size` 与主机代理限制 |
| 资源包下载 404 | 检查 `storage/app/private/resource-packs/` 是否存在且可写；本地存储设置需在后台确认 |
| 会话丢失 | 确认 `SESSION_DRIVER=file` 且 `storage/framework/sessions/` 可写 |

## 与 VPS 的差异

- 不使用 Nginx `X-Accel-Redirect`，下载模式保持 **PHP 流式**。
- 不使用数据库会话 / 缓存，避免共享主机连接数限制。
- 不需要 Cron；Pack Format 手动同步即可。
