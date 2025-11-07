# 事件和监听器对应关系示例

## 当前项目的实际配置

根据 `php artisan event:list` 的输出，可以看到：

```
App\Events\UserLoggedIn
⇂ App\Listeners\RecordUserLoginActivity@handle (ShouldQueue)
```

这说明：
- ✅ 事件 `UserLoggedIn` 已注册
- ✅ 监听器 `RecordUserLoginActivity` 已自动绑定
- ✅ 监听器标记为 `ShouldQueue`（队列处理）

## 匹配机制详解

### 1. 事件类定义
```php
// app/Events/UserLoggedIn.php
class UserLoggedIn
{
    public User $user;
    public ?string $ipAddress;
    public ?string $userAgent;
    // ...
}
```

### 2. 监听器类定义
```php
// app/Listeners/RecordUserLoginActivity.php
class RecordUserLoginActivity implements ShouldQueue
{
    // 关键：handle 方法的参数类型
    public function handle(UserLoggedIn $event): void
    {
        // ↑ 这里的 UserLoggedIn 类型提示就是匹配的关键
    }
}
```

### 3. Laravel 的自动发现过程

1. **扫描监听器**：Laravel 扫描 `app/Listeners` 目录
2. **反射检查**：使用反射检查每个监听器的 `handle()` 方法
3. **类型匹配**：找到参数类型为 `UserLoggedIn` 的监听器
4. **自动注册**：将监听器注册到对应的事件

## 工作流程图

```
用户登录
    ↓
AuthController::login()
    ↓
event(new UserLoggedIn($user, $ip, $userAgent))
    ↓
Laravel 事件系统
    ↓
查找所有监听 UserLoggedIn 事件的监听器
    ↓
发现 RecordUserLoginActivity (实现了 ShouldQueue)
    ↓
创建队列任务 → 放入 jobs 表
    ↓
返回响应给用户（登录完成）
    ↓
[异步] 队列处理器执行任务
    ↓
RecordUserLoginActivity::handle() 执行
    ↓
记录用户活动到数据库
```

## 一个事件可以有多个监听器

你可以为同一个事件添加多个监听器：

```php
// app/Listeners/SendLoginNotification.php
class SendLoginNotification implements ShouldQueue
{
    public function handle(UserLoggedIn $event): void
    {
        // 发送登录通知邮件
    }
}

// app/Listeners/UpdateLastLoginTime.php
class UpdateLastLoginTime
{
    public function handle(UserLoggedIn $event): void
    {
        // 更新最后登录时间（同步执行）
    }
}
```

两个监听器都会在 `UserLoggedIn` 事件触发时执行！

## 关键点总结

1. **类型提示是匹配的关键**：`handle(UserLoggedIn $event)` 中的类型必须与事件类完全匹配
2. **自动发现**：Laravel 11 会自动发现并注册，无需手动配置
3. **队列处理**：实现 `ShouldQueue` 接口的监听器会异步执行
4. **解耦设计**：事件和监听器完全解耦，可以轻松添加或移除监听器

