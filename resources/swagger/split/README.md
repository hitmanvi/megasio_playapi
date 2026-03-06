# OpenAPI 分割文件

此目录由 `php artisan swagger:split` 生成，将 openapi.json 按 tag 分割成小文件便于编辑。

## 目录结构

```
split/
├── base.json          # 公共部分：openapi, info, servers, tags
├── paths/             # 按 tag 分割的 paths
│   ├── Authentication.json
│   ├── Deposit.json
│   └── ...
├── components.json    # schemas, securitySchemes 等
└── README.md
```

## 工作流

1. **分割**（首次或 openapi.json 有更新时）：
   ```bash
   php artisan swagger:split
   ```

2. **编辑**：直接修改 `paths/*.json` 或 `components.json`，每个文件较小便于定位错误

3. **合并**（编辑完成后）：
   ```bash
   php artisan swagger:merge
   ```

合并会输出到 `resources/swagger/openapi.json`，Swagger UI 将使用合并后的文件。
