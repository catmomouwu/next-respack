# API 与下载协议

## Pack Format 公开 API

无需认证。

### 列出最新版本

```http
GET /api/pack-formats
```

返回最近 100 条版本映射：

```json
{
  "data": [
    { "version": "1.21.9", "type": "release", "resource_pack_format": "69.1" }
  ],
  "source": "https://minecraft.wiki/w/Pack_format",
  "note": "Pass ?version=1.21.9 or ?format=69.0 for a focused lookup."
}
```

### 按游戏版本查询

```http
GET /api/pack-formats?version=1.20.1
```

```json
{
  "data": {
    "version": "1.20.1",
    "type": "release",
    "release_date": "2023-06-12",
    "resource_pack_format": "15",
    "format_major": 15,
    "format_minor": null
  },
  "source": "https://minecraft.wiki/w/Pack_format",
  "updated_at": "2026-01-01T00:00:00+00:00"
}
```

未知版本返回 `404`。

### 按格式查询

```http
GET /api/pack-formats?format=15
```

```json
{
  "data": {
    "resource_pack_format": "15",
    "format_major": 15,
    "format_minor": null,
    "versions": [
      { "version": "1.20.1", "type": "release", "release_date": "2023-06-12" }
    ]
  },
  "source": "https://minecraft.wiki/w/Pack_format"
}
```

## 匿名下载

每个启用版本拥有独立 Token 链接：

```text
GET /dl/{token}
```

- Token 由 `APP_KEY` + 版本公开 ID + 代次做 HMAC-SHA256，Base64URL 编码。
- 数据库只保存 Token 的 SHA-256 摘要；重置 Token 后代次递增，旧链接立即失效。
- 支持 `GET` 与 `HEAD`。HEAD 返回相同的头部但不返回正文，也不计入下载统计。
- 响应头：
  - `Content-Type: application/zip`
  - `Content-Disposition: attachment; filename="..."`
  - `ETag: "<sha1>"`（基于最终存储字节）
  - `Accept-Ranges: bytes`
  - `Last-Modified`
  - `Cache-Control: public, max-age=3600`
- 条件请求：`If-None-Match` 命中返回 `304`。
- 范围请求：
  - `Range: bytes=0-99` → `206 Partial Content` + `Content-Range`
  - 越界/非法 → `416 Range Not Satisfiable` + `Content-Range: bytes */{size}`
  - 多段 Range 不拆分，返回完整文件（200）。
- 失效场景：
  - Token 不存在 / 已重置 / 文件丢失 → `404`
  - 版本或资源包被停用 → `403`

### 示例

```bash
curl -I "https://respack.example.com/dl/TOKEN"
curl -H "Range: bytes=0-1023" "https://respack.example.com/dl/TOKEN" -o part.bin
```

## server.properties 生成

版本详情页提供生成器，输出：

```properties
resource-pack=https://respack.example.com/dl/TOKEN
resource-pack-sha1=<sha1>
resource-pack-prompt=资源包名称
```

将内容复制到服务器 `server.properties`，玩家进入时自动提示下载。

## 错误格式

API 404：

```json
{ "message": "Unknown Minecraft version." }
```

下载错误为纯文本，状态码语义见上。
