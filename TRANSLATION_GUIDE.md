# 多语言翻译系统使用指南

## 概述

这个多语言翻译系统允许你为任何模型添加多语言支持，特别适用于需要支持多种语言的字段，如标签名称、游戏名称等。

## 核心组件

### 1. Translation 模型
存储所有翻译数据的核心模型，包含以下字段：
- `translatable_type`: 模型类名
- `translatable_id`: 模型ID
- `field`: 字段名
- `locale`: 语言代码
- `value`: 翻译内容

### 2. Translatable Trait
提供多语言功能的核心trait，包含以下方法：

#### 基本方法
- `getTranslation($field, $locale)`: 获取指定字段和语言的翻译
- `setTranslation($field, $value, $locale)`: 设置指定字段和语言的翻译
- `getTranslations($field)`: 获取指定字段的所有翻译
- `setTranslations($field, $translations)`: 批量设置翻译

#### 高级方法
- `getTranslatedAttribute($field, $locale, $fallbackLocale)`: 获取翻译，支持回退语言
- `getAvailableLocales($field)`: 获取字段可用的语言列表
- `deleteTranslations($field)`: 删除指定字段的所有翻译
- `deleteAllTranslations()`: 删除模型的所有翻译

#### 魔术方法
- `$model->field_locale`: 直接访问翻译，如 `$tag->name_en`, `$tag->name_zh_cn`

## 使用方法

### 1. 在模型中使用

```php
<?php

namespace App\Models;

use App\Traits\Translatable;

class Tag extends Model
{
    use Translatable;

    protected $fillable = ['name', 'type'];

    // 可选：添加便捷方法
    public function getName(?string $locale = null): ?string
    {
        return $this->getTranslatedAttribute('name', $locale);
    }

    public function setName(string $name, ?string $locale = null): void
    {
        $this->setTranslation('name', $name, $locale);
    }
}
```

### 2. 设置翻译

```php
$tag = Tag::create(['name' => 'action', 'type' => 'category']);

// 单个翻译
$tag->setTranslation('name', 'Action', 'en');
$tag->setTranslation('name', '动作', 'zh-CN');

// 批量翻译
$tag->setTranslations('name', [
    'en' => 'Action',
    'zh-CN' => '动作',
    'ja' => 'アクション',
    'ko' => '액션'
]);
```

### 3. 获取翻译

```php
// 获取指定语言的翻译
$englishName = $tag->getTranslation('name', 'en');
$chineseName = $tag->getTranslation('name', 'zh-CN');

// 获取当前语言环境的翻译
app()->setLocale('zh-CN');
$currentName = $tag->getTranslatedAttribute('name');

// 使用魔术方法
$englishName = $tag->name_en;
$chineseName = $tag->name_zh_cn;

// 获取所有翻译
$allNames = $tag->getTranslations('name');
// 返回: ['en' => 'Action', 'zh-CN' => '动作', 'ja' => 'アクション']
```

### 4. API 使用示例

```php
// 创建带翻译的标签
POST /api/tags
{
    "name": "action",
    "type": "category",
    "translations": {
        "en": "Action",
        "zh-CN": "动作",
        "ja": "アクション"
    }
}

// 获取标签及其翻译
GET /api/tags/1
{
    "tag": {
        "id": 1,
        "name": "action",
        "type": "category"
    },
    "translations": {
        "name": {
            "en": "Action",
            "zh-CN": "动作",
            "ja": "アクション"
        }
    },
    "available_locales": ["en", "zh-CN", "ja"]
}
```

## 数据库结构

### translations 表
```sql
CREATE TABLE translations (
    id BIGINT PRIMARY KEY,
    translatable_type VARCHAR(255),
    translatable_id BIGINT,
    field VARCHAR(255),
    locale VARCHAR(10),
    value TEXT,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    INDEX translation_lookup (translatable_type, translatable_id, field, locale),
    UNIQUE KEY translation_unique (translatable_type, translatable_id, field, locale)
);
```

## 性能优化建议

1. **预加载翻译**: 使用 `with('translations')` 预加载翻译关系
2. **缓存翻译**: 对频繁访问的翻译进行缓存
3. **批量操作**: 使用 `setTranslations()` 而不是多次调用 `setTranslation()`

## 扩展功能

你可以轻松扩展这个系统来支持：
- 更多字段的多语言支持
- 翻译历史记录
- 翻译审核工作流
- 自动翻译集成
- 翻译统计和分析
