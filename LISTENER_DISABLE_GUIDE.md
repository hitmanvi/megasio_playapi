# 禁用事件监听器但保留代码的几种方案

## 方案 1：使用 EventServiceProvider 手动控制（推荐）

### 优点
- ✅ 精确控制哪些监听器启用
- ✅ 代码完全保留，只需注释或删除映射
- ✅ 清晰明了，易于维护
- ✅ 不需要修改监听器代码本身

### 实现步骤

1. **创建 EventServiceProvider**（已完成）
   ```bash
   php artisan make:provider EventServiceProvider
   ```

2. **禁用自动发现，手动注册**
   ```php
   class EventServiceProvider extends ServiceProvider
   {
       protected $listen = [
           OrderCompleted::class => [
               RecordUserRecentGame::class,
               UpdateUserWager::class,
               // CreateRollover::class, // 已禁用，但代码保留
           ],
       ];

       public function shouldDiscoverEvents(): bool
       {
           return false; // 禁用自动发现
       }
   }
   ```

3. **注册 EventServiceProvider**
   在 `bootstrap/providers.php` 中添加：
   ```php
   return [
       App\Providers\AppServiceProvider::class,
       App\Providers\EventServiceProvider::class, // 添加这行
   ];
   ```

### 禁用监听器的方法
- **方法 1：注释掉映射**（推荐）
  ```php
  OrderCompleted::class => [
      RecordUserRecentGame::class,
      // CreateRollover::class, // 已禁用
  ],
  ```

- **方法 2：删除映射**
  ```php
  OrderCompleted::class => [
      RecordUserRecentGame::class,
      // CreateRollover 已从列表中移除
  ],
  ```

---

## 方案 2：在监听器内部添加条件判断

### 优点
- ✅ 不需要创建 EventServiceProvider
- ✅ 可以基于配置或环境变量动态控制
- ✅ 代码保留，只是不执行逻辑

### 缺点
- ❌ 监听器仍然会被注册（占用资源）
- ❌ 队列任务仍然会被创建（浪费队列资源）
- ❌ 需要修改每个监听器代码

### 实现示例

```php
class CreateRollover implements ShouldQueue
{
    public function handle(DepositCompleted $event): void
    {
        // 检查是否启用该功能
        if (!config('features.create_rollover_enabled', false)) {
            return; // 直接返回，不执行逻辑
        }

        // 原有的业务逻辑
        $rollover = Rollover::create([...]);
    }
}
```

在 `config/features.php` 中配置：
```php
return [
    'create_rollover_enabled' => env('FEATURE_CREATE_ROLLOVER_ENABLED', false),
];
```

---

## 方案 3：使用环境变量控制（结合方案 2）

### 实现示例

```php
class CreateRollover implements ShouldQueue
{
    public function handle(DepositCompleted $event): void
    {
        // 通过环境变量控制
        if (!env('ENABLE_CREATE_ROLLOVER', false)) {
            return;
        }

        // 业务逻辑
    }
}
```

在 `.env` 中：
```env
ENABLE_CREATE_ROLLOVER=false
```

---

## 方案 4：重命名监听器类（临时方案）

### 实现
将监听器类重命名，Laravel 就不会自动发现它：

```php
// 重命名前
class CreateRollover implements ShouldQueue { ... }

// 重命名后
class CreateRolloverDisabled implements ShouldQueue { ... }
```

### 缺点
- ❌ 需要修改类名和文件名
- ❌ 如果将来要启用，需要改回来
- ❌ 不够优雅

---

## 方案 5：移动到其他目录（临时方案）

### 实现
将监听器文件移动到其他目录（如 `app/Listeners/Disabled/`），Laravel 就不会扫描到它。

### 缺点
- ❌ 需要移动文件
- ❌ 命名空间可能需要调整
- ❌ 不够优雅

---

## 推荐方案对比

| 方案 | 代码保留 | 不注册监听器 | 不创建队列任务 | 易于维护 | 推荐度 |
|------|---------|------------|--------------|---------|--------|
| EventServiceProvider | ✅ | ✅ | ✅ | ✅ | ⭐⭐⭐⭐⭐ |
| 条件判断 | ✅ | ❌ | ❌ | ⚠️ | ⭐⭐⭐ |
| 环境变量 | ✅ | ❌ | ❌ | ⚠️ | ⭐⭐⭐ |
| 重命名类 | ✅ | ✅ | ✅ | ❌ | ⭐⭐ |
| 移动目录 | ✅ | ✅ | ✅ | ❌ | ⭐⭐ |

## 当前项目推荐：使用 EventServiceProvider

**已实现的配置：**

1. ✅ 创建了 `EventServiceProvider`
2. ✅ 禁用了自动发现（`shouldDiscoverEvents()` 返回 `false`）
3. ✅ 手动注册需要启用的监听器
4. ✅ 已禁用的监听器（如 `CreateRollover`）在映射中被注释，但代码保留

**禁用监听器示例：**
```php
protected $listen = [
    DepositCompleted::class => [
        CreateInvitationDepositReward::class,
        CreateDepositBonusTask::class,
        // CreateRollover::class, // 已禁用，但代码保留在 app/Listeners/CreateRollover.php
    ],
];
```

**启用监听器：**
只需取消注释即可：
```php
DepositCompleted::class => [
    CreateInvitationDepositReward::class,
    CreateDepositBonusTask::class,
    CreateRollover::class, // 取消注释即可启用
],
```

## 验证方法

运行以下命令查看注册的监听器：
```bash
php artisan event:list
```

已禁用的监听器不会出现在列表中。
