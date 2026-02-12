# 事件和监听器重复执行问题分析

## 问题描述
事件监听器被执行了两次，导致业务逻辑重复处理。

## 根本原因

### 1. Laravel 11 自动发现机制
Laravel 11 **默认启用**事件自动发现功能，会：
- 自动扫描 `app/Listeners` 目录
- 通过反射检查每个监听器的 `handle()` 方法参数类型
- 自动将监听器注册到对应的事件

### 2. 手动注册（AppServiceProvider）
在 `AppServiceProvider::configureEventListeners()` 中手动注册了所有监听器：
```php
Event::listen(OrderCompleted::class, UpdateRolloverProgress::class);
Event::listen(OrderCompleted::class, CheckBonusTaskDeplete::class);
// ... 等等
```

### 3. 双重注册导致的问题
- 每个监听器被注册了**两次**（自动发现 + 手动注册）
- 事件触发时，每个监听器会创建**两个队列任务**
- 队列 worker 处理这两个任务，导致逻辑执行**两次**

## 工作流程说明

### 正常流程（单个实例）
```
1. Web API 实例接收请求
   ↓
2. 触发事件：event(new OrderCompleted($order))
   ↓
3. Laravel 查找所有注册的监听器
   ↓
4. 监听器实现 ShouldQueue → 创建队列任务
   ↓
5. 任务放入 jobs 表
   ↓
6. 队列 worker 从 jobs 表取出任务
   ↓
7. 执行监听器的 handle() 方法
```

### 当前问题流程（双重注册）
```
1. Web API 实例接收请求
   ↓
2. 触发事件：event(new OrderCompleted($order))
   ↓
3. Laravel 查找所有注册的监听器
   ↓
4. 发现监听器被注册了两次（自动发现 + 手动注册）
   ↓
5. 为每个监听器创建两个队列任务
   ↓
6. 两个任务都放入 jobs 表
   ↓
7. 队列 worker 处理第一个任务 → 执行一次
   ↓
8. 队列 worker 处理第二个任务 → 又执行一次 ❌
```

## 解决方案

### 方案 1：移除手动注册（推荐）
移除 `AppServiceProvider` 中的手动注册，完全依赖 Laravel 的自动发现机制。

**优点：**
- 代码更简洁
- 减少维护成本
- 符合 Laravel 11 最佳实践

**操作：**
删除 `AppServiceProvider::configureEventListeners()` 方法中的所有 `Event::listen()` 调用。

### 方案 2：禁用自动发现
保留手动注册，禁用 Laravel 的自动发现。

**缺点：**
- 需要手动维护所有事件-监听器映射
- 新增监听器时需要手动注册
- 代码冗余

**操作：**
创建 `EventServiceProvider` 并设置 `shouldDiscoverEvents()` 返回 `false`。

## 推荐方案：移除手动注册

Laravel 11 的自动发现机制已经足够强大，不需要手动注册。只需要确保：
1. 监听器类在 `app/Listeners` 目录下
2. 监听器的 `handle()` 方法有正确的类型提示
3. 监听器实现 `ShouldQueue`（如果需要队列处理）

## 验证方法

运行以下命令查看注册的监听器：
```bash
php artisan event:list
```

如果看到监听器被列出两次，说明确实存在双重注册问题。

## 注意事项

### 队列处理
- 所有监听器都实现了 `ShouldQueue`，会异步执行
- Web API 实例：只负责触发事件和创建队列任务
- 队列 Worker 实例：只负责处理队列任务
- **两个实例都应该加载相同的代码**，但只有 Web API 实例会触发事件

### 部署架构建议
```
Web API 实例（处理 HTTP 请求）
  ├─ 接收请求
  ├─ 执行业务逻辑
  ├─ 触发事件 → 创建队列任务
  └─ 返回响应

队列 Worker 实例（处理队列任务）
  ├─ 从队列取出任务
  ├─ 执行监听器逻辑
  └─ 完成任务
```

两个实例共享：
- 相同的代码库
- 相同的数据库
- 相同的队列（jobs 表）
