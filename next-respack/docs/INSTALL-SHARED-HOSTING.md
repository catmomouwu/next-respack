# 共享主机部署

本方案面向 cPanel、宝塔、DirectAdmin、Plesk 等常见共享主机：**无需 SSH、Composer、Node、Cron、Redis、Supervisor、Docker**。发行包 `next-respack-shared-hosting.zip` 已包含 `vendor/`、已构建的前端资源、`.env` 模板与部署说明，**解压即用**。

## 压缩包结构

```text
next-respack-shared-hosting.zip
├── 部署说明.txt
├── READ-ME-FIRST.txt
├── next-respack/          # 完整应用（放在 Web 根目录之外）
│   ├── app/  bootstrap/  config/  database/
│   ├── lang/  resources/  routes/  storage/
│   ├── vendor/  .env  .env.example  artisan
│   └── public/            # 仅作为源，不直接对外
└── public_html/           # 站点文档根目录
    ├── index.php          # 自动定位应用的包装器
    ├── .htaccess
    ├── .user.ini          # 512M 上传上限
    └── build/
```

`public_html` 中**只允许**入口文件、`.htaccess`、`.user.ini` 与 `build/` 静态资源；其余文件全部在 `next-respack/` 内。

## 步骤

### 1. 解压到正确位置

把压缩包解压到**包含 `public_html` 的那个目录**，解压后二者为同级：

```text
你的账号目录/
├── next-respack/
└── public_html/
```

- 若网站根目录名为 `www`、`httpdocs`、`wwwroot`，把 `public_html` 文件夹改名为对应名称即可，**无需修改代码**。
- 子域名 / 附加域名：解压到该域名的目录（即包含其 `public_html` 的那一层）。
- `index.php` 会自动在上一级查找 `next-respack/`，找不到时会额外扫描同级目录，因此常见目录结构都能直接运行。

### 2. 权限

在文件管理器中设置：

- `next-respack/storage/`、`next-respack/bootstrap/cache/`、`next-respack/database/`：775（可写）。
- `next-respack/.env`：600（包内已附带无密钥模板，安装时自动改写）。
- 若主机把项目根目录设为只读，只要 `.env` 文件本身可写即可完成安装。

### 3. PHP 配置

安装包已在 `public_html/.user.ini` 中设置：

```ini
upload_max_filesize = 512M
post_max_size = 520M
memory_limit = 256M
max_execution_time = 300
```

若主机忽略 `.user.ini`，请在控制面板设置相同值。确认 PHP 版本为 8.3+，并启用 `zip`、`pdo_mysql`、`mbstring`、`openssl`、`fileinfo`、`curl`。

### 4. 数据库

在控制面板创建数据库与用户，记录主机、端口、库名、用户名、密码。安装向导会测试连接与建表权限。

### 5. 安装

访问站点根域名，按 [INSTALL.md](INSTALL.md) 完成向导。安装器会自动写入 `.env`，无需手动配置。

### 6. HTTPS

在主机面板启用 Let's Encrypt / 自带证书，并开启强制 HTTPS。安装时填写的站点 URL 必须是 `https://`。

### 7. 收尾

- 删除 `public_html` 下多余示例文件，保留 `index.php`、`.htaccess`、`.user.ini`、`build/`。
- 确认 `next-respack/storage/app/installed.lock` 已生成，`/install` 返回 404。
- 设置主机每日自动备份（数据库 + `next-respack/storage/` + `.env`）。

## 常见问题

| 问题 | 处理 |
| --- | --- |
| 提示 application directory not found | 确认 `next-respack/` 与 `public_html/` 为同级；见页面提示的期望路径 |
| 404 / 500 | 检查文档根指向、`.htaccess` 是否启用 `mod_rewrite` |
| 页面无样式 | `public_html/build/manifest.json` 与 `build/assets/` 是否完整 |
| 安装器无法写 `.env` | 将 `.env` 设为可写；或临时给项目根目录写权限 |
| 上传大文件失败 | PHP 的 `upload_max_filesize`、`post_max_size` 与主机代理限制 |
| 资源包下载 404 | 检查 `storage/app/private/resource-packs/` 是否存在且可写；本地存储设置需在后台确认 |
| 会话丢失 | 确认 `SESSION_DRIVER=file` 且 `storage/framework/sessions/` 可写 |

## 与 VPS 的差异

- 不使用 Nginx `X-Accel-Redirect`，下载模式保持 **PHP 流式**。
- 不使用数据库会话 / 缓存，避免共享主机连接数限制。
- 不需要 Cron；Pack Format 手动同步即可。
