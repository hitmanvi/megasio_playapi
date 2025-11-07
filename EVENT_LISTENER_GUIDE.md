# Laravel 事件和监听器工作机制

## 1. 基本概念

### 事件 (Event)
事件是应用中发生的动作，比如用户登录、订单创建等。事件类通常放在 `app/Events` 目录下。

### 监听器 (Listener)
监听器是响应事件的类，当事件被触发时，监听器会执行相应的操作。监听器通常放在 `app/Listeners` 目录下。

## 2. 事件和监听器的对应关系

### Laravel 11 自动发现机制

Laravel 11 使用**自动发现**机制，通过以下规则自动匹配事件和监听器：

1. **命名约定**：
   - 事件类：`App\Events\UserLoggedIn`
   - 监听器类：`App\Listeners\RecordUserLoginActivity`
   - Laravel 会自动扫描 `app/Listeners` 目录下的所有监听器

2. **类型提示匹配**：
   - 监听器的 `handle()` 方法的参数类型必须与事件类匹配
   ```php
   // 监听器中的 handle 方法
   public function handle(UserLoggedIn $event): void
   {
       // 这里的 UserLoggedIn 类型提示就是匹配的关键
   }
   ```

3. **自动注册**：
   - Laravel 在启动时会自动扫描所有监听器
   - 通过反射检查 `handle()` 方法的参数类型
   - 自动将监听器注册到对应的事件上

## 3. 工作流程

### 步骤 1: 触发事件
```php
// 在控制器中
event(new UserLoggedIn($user, $request->ip(), $request->userAgent()));
```

### 步骤 2: Laravel 事件系统处理
1. Laravel 接收到事件实例
2. 查找所有注册到该事件的监听器
3. 如果监听器实现了 `ShouldQueue`，将任务放入队列
4. 如果监听器没有实现 `ShouldQueue`，同步执行

### 步骤 3: 执行监听器
```php
// 监听器的 handle 方法被调用
public function handle(UserLoggedIn $event): void
{
    // 处理逻辑
}
```

## 4. 队列监听器的工作方式

当监听器实现 `ShouldQueue` 接口时：

1. **任务入队**：
   - 事件触发时，Laravel 不会立即执行监听器
   - 而是创建一个队列任务，放入 `jobs` 表（如果使用 database 驱动）

2. **队列处理**：
   - 需要运行 `php artisan queue:work` 来处理队列任务
   - 队列处理器会从 `jobs` 表中取出任务并执行

3. **序列化**：
   - 事件对象会被序列化存储到队列中
   - 事件类需要使用 `SerializesModels` trait 来正确序列化模型

4. **重试机制**：
   - 如果任务失败，Laravel 会自动重试（默认最多 3 次）
   - 重试次数可以在监听器中配置

## 5. 手动注册事件和监听器（可选）

虽然 Laravel 11 支持自动发现，但你也可以手动注册：

### 创建 EventServiceProvider

```bash
php artisan make:provider EventServiceProvider
```

### 在 EventServiceProvider 中注册

```php
<?php

namespace App\Providers;

use App\Events\UserLoggedIn;
use App\Listeners\RecordUserLoginActivity;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    /**
     * 事件监听器映射
     */
    protected $listen = [
        UserLoggedIn::class => [
            RecordUserLoginActivity::class,
        ],
    ];

    /**
     * 注册任何事件订阅者
     */
    public function shouldDiscoverEvents(): bool
    {
        return true; // 启用自动发现
    }
}
```

### 在 bootstrap/providers.php 中注册

```php
<?php

return [
    App\Providers\AppServiceProvider::class,
    App\Providers\EventServiceProvider::class, // 添加这行
];
```

## 6. 当前项目的配置

在你的项目中：

1. **自动发现已启用**：Laravel 11 默认启用自动发现
2. **事件**：`App\Events\UserLoggedIn`
3. **监听器**：`App\Listeners\RecordUserLoginActivity`
4. **匹配方式**：通过 `handle(UserLoggedIn $event)` 方法的类型提示自动匹配

## 7. 验证事件和监听器的绑定

你可以运行以下命令查看所有注册的事件和监听器：

```bash
php artisan event:list
```

这会显示所有已注册的事件及其对应的监听器。

## 8. 调试技巧

1. **查看队列任务**：
   ```bash
   # 查看队列中的任务
   php artisan queue:work --verbose
   ```

2. **查看失败的任务**：
   ```bash
   # 查看失败的任务
   php artisan queue:failed
   ```

3. **手动触发事件测试**：
   ```php
   // 在 tinker 中测试
   php artisan tinker
   event(new \App\Events\UserLoggedIn($user, '127.0.0.1', 'test'));
   ```

## 总结

- Laravel 通过**类型提示**自动匹配事件和监听器
- 监听器的 `handle()` 方法参数类型必须与事件类匹配
- 实现 `ShouldQueue` 接口的监听器会异步执行
- Laravel 11 默认启用自动发现，无需手动注册

