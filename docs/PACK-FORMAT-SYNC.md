# Minecraft Pack Format 数据

Next Respack 内置 Minecraft Java 版资源包格式数据，用于在上传时识别 `pack.mcmeta` 中的格式并映射到具体游戏版本。

## 数据来源

- 页面：<https://minecraft.wiki/w/Pack_format>
- API：<https://minecraft.wiki/api.php>（`action=parse&page=Pack_format&prop=text`）
- 内置快照：`database/data/resource_pack_formats.json`（安装时导入，离线可用）

## 数据结构

- `minecraft_pack_formats`：一种格式（主版本 + 可选次版本），如 `15`、`69.1`。
- `minecraft_pack_format_versions`：格式与游戏版本的映射，含版本类型：`release`、`snapshot`、`prerelease`、`release_candidate`、`experimental`。
- `minecraft_pack_format_sync_logs`：每次同步/导入的结果、差异统计、错误信息。
- `minecraft_pack_format_histories`：同步前快照，支持回滚。

## 后台操作

路径：**后台 → Minecraft 数据**。

| 操作 | 说明 |
| --- | --- |
| 立即同步 | 从 minecraft.wiki 拉取并解析版本表 |
| 导入 JSON / CSV | 粘贴内容或上传文件；要求 ≥ 30 条有效记录 |
| 历史记录 | 查看每次同步的差异与状态 |
| 回滚 | 将数据恢复到某次同步前的快照 |
| 人工修正 | 编辑单条版本或格式，标记 `is_manual`，后续同步不会覆盖 |
| 删除 | 仅允许删除人工记录；同步记录由同步逻辑维护 |

## 失败保护

- 网络失败、解析失败或有效记录少于 30 条时，**不会覆盖现有数据**，只写入失败日志。
- 同步写入在事务中完成；失败自动回滚。
- 自动同步默认关闭；即使开启，也最多每天一次且由管理员访问触发，失败不影响页面。
- 手动同步始终可用，共享主机无需 Cron。

## CSV 格式

```csv
version_name,version_type,format_value,format_major,format_minor
1.20.1,release,15,15,
1.21.9,release,69.1,69,1
23w31a,snapshot,16,16,
```

`format_major` / `format_minor` 可省略，系统会从 `format_value` 推导。`version_type` 可省略，系统按版本名自动分类。

## JSON 格式

```json
{
  "records": [
    { "version_name": "1.20.1", "version_type": "release", "format_value": "15" },
    { "version_name": "1.21.9", "version_type": "release", "format_value": "69.1" }
  ]
}
```

也可直接提交记录数组。

## 公开查询

- 页面：`/minecraft/pack-formats`
- API：`/api/pack-formats`，详见 [API.md](API.md)

## 上传识别逻辑

1. 读取根目录 `pack.mcmeta`，解析 `pack_format`、`min_format`、`max_format`、`supported_formats`。
2. 在版本表中查找对应格式；找到则记录检测到的游戏版本。
3. 未找到 → `FORMAT_UNKNOWN`（可保存并显示警告）。
4. 元数据缺失或无法解析 → `METADATA_INVALID`（拒绝保存）。
5. 存在额外警告（如同时使用新旧字段）→ `VALID_WITH_WARNING`。
