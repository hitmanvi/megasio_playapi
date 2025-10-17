# Tag列表API使用示例

## 🚀 API端点

### 1. 获取所有标签列表
```
GET /api/tags?locale=zh-CN
```

**响应示例：**
```json
{
    "success": true,
    "data": [
        {
            "id": 1,
            "name": "action",
            "type": "category",
            "translated_name": "动作",
            "all_translations": {
                "en": "Action",
                "zh-CN": "动作",
                "ja": "アクション",
                "ko": "액션"
            },
            "available_locales": ["en", "zh-CN", "ja", "ko"],
            "created_at": "2025-01-17T06:28:22.000000Z",
            "updated_at": "2025-01-17T06:28:22.000000Z"
        },
        {
            "id": 2,
            "name": "rpg",
            "type": "category",
            "translated_name": "角色扮演",
            "all_translations": {
                "en": "RPG",
                "zh-CN": "角色扮演",
                "ja": "RPG",
                "ko": "RPG"
            },
            "available_locales": ["en", "zh-CN", "ja", "ko"],
            "created_at": "2025-01-17T06:28:22.000000Z",
            "updated_at": "2025-01-17T06:28:22.000000Z"
        }
    ],
    "meta": {
        "total": 2,
        "locale": "zh-CN",
        "fallback_locale": "en"
    }
}
```

### 2. 根据类型获取标签
```
GET /api/tags/type/category?locale=en
```

**响应示例：**
```json
{
    "success": true,
    "data": [
        {
            "id": 1,
            "name": "action",
            "type": "category",
            "translated_name": "Action",
            "all_translations": {
                "en": "Action",
                "zh-CN": "动作",
                "ja": "アクション",
                "ko": "액션"
            }
        },
        {
            "id": 2,
            "name": "rpg",
            "type": "category",
            "translated_name": "RPG",
            "all_translations": {
                "en": "RPG",
                "zh-CN": "角色扮演",
                "ja": "RPG",
                "ko": "RPG"
            }
        }
    ],
    "meta": {
        "type": "category",
        "total": 2,
        "locale": "en"
    }
}
```

### 3. 搜索标签（支持多语言搜索）
```
GET /api/tags/search?q=动作&locale=zh-CN&type=category
```

**响应示例：**
```json
{
    "success": true,
    "data": [
        {
            "id": 1,
            "name": "action",
            "type": "category",
            "translated_name": "动作",
            "all_translations": {
                "en": "Action",
                "zh-CN": "动作",
                "ja": "アクション",
                "ko": "액션"
            },
            "match_type": "translated_name"
        }
    ],
    "meta": {
        "query": "动作",
        "locale": "zh-CN",
        "type": "category",
        "total": 1
    }
}
```

### 4. 获取单个标签详情
```
GET /api/tags/1
```

**响应示例：**
```json
{
    "tag": {
        "id": 1,
        "name": "action",
        "type": "category",
        "created_at": "2025-01-17T06:28:22.000000Z",
        "updated_at": "2025-01-17T06:28:22.000000Z"
    },
    "translations": {
        "name": {
            "en": "Action",
            "zh-CN": "动作",
            "ja": "アクション",
            "ko": "액션"
        }
    },
    "available_locales": ["en", "zh-CN", "ja", "ko"]
}
```

### 5. 创建标签
```
POST /api/tags
Content-Type: application/json

{
    "name": "adventure",
    "type": "category",
    "translations": {
        "en": "Adventure",
        "zh-CN": "冒险",
        "ja": "アドベンチャー",
        "ko": "모험"
    }
}
```

**响应示例：**
```json
{
    "message": "Tag created successfully",
    "tag": {
        "id": 7,
        "name": "adventure",
        "type": "category",
        "created_at": "2025-01-17T06:28:22.000000Z",
        "updated_at": "2025-01-17T06:28:22.000000Z"
    },
    "translations": {
        "en": "Adventure",
        "zh-CN": "冒险",
        "ja": "アドベンチャー",
        "ko": "모험"
    }
}
```

## 🧪 测试数据

你可以通过以下方式创建测试数据：

```php
// 在控制器或Seeder中创建测试标签
$actionTag = Tag::create(['name' => 'action', 'type' => 'category']);
$actionTag->setTranslations('name', [
    'en' => 'Action',
    'zh-CN' => '动作',
    'ja' => 'アクション',
    'ko' => '액션'
]);
```

## 📝 使用说明

### 查询参数
- `locale`: 指定返回语言（可选，默认为应用当前语言）
- `q`: 搜索关键词（搜索接口必需）
- `type`: 标签类型过滤（可选）

### 响应字段说明
- `name`: 原始字段值
- `translated_name`: 指定语言的翻译值（带回退机制）
- `all_translations`: 所有语言的翻译
- `available_locales`: 可用的语言列表
- `match_type`: 搜索匹配类型（original_name/translated_name/other_translation）

### 性能优化
- 使用 `with('translations')` 预加载翻译关系
- 支持分页（可扩展）
- 索引优化查询性能

## 🔧 前端集成示例

### JavaScript/TypeScript
```javascript
// 获取中文标签列表
const response = await fetch('/api/tags?locale=zh-CN');
const data = await response.json();

// 显示标签
data.data.forEach(tag => {
    console.log(`${tag.translated_name} (${tag.name})`);
});

// 搜索标签
const searchResponse = await fetch('/api/tags/search?q=动作&locale=zh-CN');
const searchData = await searchResponse.json();
```

### Vue.js
```vue
<template>
  <div>
    <h2>标签列表</h2>
    <div v-for="tag in tags" :key="tag.id" class="tag-item">
      <span class="tag-name">{{ tag.translated_name }}</span>
      <span class="tag-original">({{ tag.name }})</span>
    </div>
  </div>
</template>

<script>
export default {
  data() {
    return {
      tags: []
    }
  },
  async mounted() {
    const response = await fetch('/api/tags?locale=zh-CN');
    const data = await response.json();
    this.tags = data.data;
  }
}
</script>
```

### React
```jsx
import { useState, useEffect } from 'react';

function TagList() {
  const [tags, setTags] = useState([]);
  
  useEffect(() => {
    fetch('/api/tags?locale=zh-CN')
      .then(response => response.json())
      .then(data => setTags(data.data));
  }, []);
  
  return (
    <div>
      <h2>标签列表</h2>
      {tags.map(tag => (
        <div key={tag.id} className="tag-item">
          <span className="tag-name">{tag.translated_name}</span>
          <span className="tag-original">({tag.name})</span>
        </div>
      ))}
    </div>
  );
}
```
