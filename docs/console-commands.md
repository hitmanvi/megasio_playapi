# Artisan 命令说明

本文说明 `app/Console/Commands` 下各命令的用途。调度定义见 `routes/console.php`（Laravel 调度时区为 `config/app.php` 的 `timezone`）。

---

## 定时任务总览

以下命令已注册在 **Laravel Schedule** 中：

| 命令 | 频率 | 说明 |
|------|------|------|
| `tokens:clean-expired` | 每日 1 次 | `daily()`，默认在每日 **00:00** 运行 |
| `bonus-task:expire` | 每日 1 次 | `daily()` |
| `orders:archive` | 每日 1 次 | `daily()` |
| `invitation:rewards:generate` | 每日 1 次 | `dailyAt('02:00')`，每日 **02:00** |
| `import:funky_games` | 每日 1 次 | `daily()` |
| `weekly-cashback:flush-buffer` | 每分钟 | `everyMinute()` |
| `weekly-cashback:calculate` | 每周 1 次 | `weeklyOn(4, '02:20')` → **每周四 02:20** |
| `weekly-cashback:remind-unclaimed` | 每周 1 次 | `weeklyOn(4, '00:00')` → **每周四 00:00** |

> `weeklyOn` 的星期与 Carbon 一致：**0=周日，1=周一，…，4=周四**。

`php artisan schedule:list` 可查看本机合并后的实际 Cron 表达式。

未出现在上表中的命令为 **仅手动 / 按需执行**，不随 `schedule:run` 自动跑。

---

## 测试与联调

开发、调试或造数使用；**不列入生产定时任务**。

| 命令 | 作用 |
|------|------|
| `test:create-rollover` | 为指定用户创建 rollover 记录（可指定货币、金额、来源类型等） |
| `balance:generate` | 为指定用户生成 / 调整余额记录 |
| `brands-themes:generate-test` | 随机生成测试品牌与主题数据 |
| `test:event-services` | 联调 Kochava、Facebook Conversions 等事件上报，可自定义事件名 |
| `test:opensearch-replay-events` | 按库内数据重放事件到 OpenSearch（注册/登录/充提等） |
| `games:generate-test` | 随机生成指定数量的测试游戏数据 |
| `test:generate-invitation-rewards` | 为指定用户邀请关系生成测试奖励数据 |
| `user:generate-recent-games` | 为指定用户生成「最近游玩」记录 |
| `test:create-weekly-cashback` | 为指定用户创建多周期 weekly cashback 测试数据 |
| `test:generate-transactions` | 生成测试交易数据（自动创建依赖实体） |
| `test:google-login` | 使用 Google `id_token` 验证并走登录/注册流程 |
| `test:generate-bonus-tasks` | 为指定用户生成测试奖励任务数据 |
| `test:sopay-callback` | 模拟/测试 Sopay 支付回调（可覆盖回调 URL） |
| `test:generate-notifications` | 为指定用户生成测试通知 |
| `test:opensearch-event` | 单条/示例事件写入 OpenSearch（如注册事件） |
| `test:opensearch-backfill` | 将 users、deposits、withdraws 等批量回填到 OpenSearch |

参数与选项以 `php artisan {命令} --help` 为准。

---

## 周返利（Weekly Cashback）

| 命令 | Schedule | 作用 |
|------|----------|------|
| `weekly-cashback:flush-buffer` | 每分钟 | 将内存/缓冲中的周返利数据刷入数据库 |
| `weekly-cashback:calculate` | 每周四 02:20 | 计算上周记录，生成 rate/amount 并标为可领取（claimable） |
| `weekly-cashback:remind-unclaimed` | 每周四 00:00 | 对仍为 claimable 且未领取的用户发送提醒通知 |

---

## 邀请奖励

| 命令 | Schedule | 作用 |
|------|----------|------|
| `invitation:rewards:generate` | 每日 02:00 | 按用户投注为指定**日期**生成邀请奖励数据（可带 `date` 参数手跑某一天） |
| `invitation:rewards:pay-pending` | 未列入 | 为已完成 KYC 的用户发放待付邀请奖励（pending） |

---

## 奖励任务

| 命令 | Schedule | 作用 |
|------|----------|------|
| `bonus-task:expire` | 每日 | 将到期 bonus task 标记为 `expired` |

---

## 游戏与数据同步

| 命令 | Schedule | 作用 |
|------|----------|------|
| `import:funky_games` | 每日 | 从 Funky 聚合商同步/导入游戏列表 |
| `games:update-thumbnails` | 未列入 | 从 JSON URL 批量更新游戏缩略图（`out_id` → 图片 URL 映射） |

---

## 订单、Token 与数据维护

| 命令 | Schedule | 作用 |
|------|----------|------|
| `orders:archive` | 每日 | 归档 N 天前已完成的订单（`--days`，默认 30） |
| `tokens:clean-expired` | 每日 | 清理过期的游戏提供商 token |

---

## 系统与内容初始化

| 命令 | Schedule | 作用 |
|------|----------|------|
| `init:all` | 未列入 | 按顺序执行：`init:setting` → `init:agent` → `init:site-links` → `init:game-groups` → `init:opensearch` |
| `init:setting` | 未列入 | 从 `config/setting.php` 初始化系统设置项 |
| `init:agent` | 未列入 | 初始化默认 agent（`noagent`）及无邀请码用户可用的 agent_link |
| `init:game-groups` | 未列入 | 创建默认游戏分组：Recommended、Support Bonus（已存在则跳过） |
| `init:site-links` | 未列入 | 初始化固定站点链接 key（不可删，URL 可后台改） |
| `init:opensearch` | 未列入 | 按 `config/opensearch.php` 的 `index_templates` 在 OpenSearch 中创建/更新索引模板 |

---

## 支付与 Sopay

| 命令 | Schedule | 作用 |
|------|----------|------|
| `sopay:replay-callback` | 未列入 | 按 `SopayCallbackLog` 中存储的记录 **重放** 一次 Sopay 回调（需日志 ID） |

---

## API 文档（Swagger / OpenAPI）

| 命令 | Schedule | 作用 |
|------|----------|------|
| `swagger:split` | 未列入 | 将根 `openapi.json` 按 tag 拆成多个小文件，便于维护 |
| `swagger:merge` | 未列入 | 将拆分后的 OpenAPI 文件合并回 `openapi.json` |

---

## 其他

| 命令 | Schedule | 作用 |
|------|----------|------|
| `users:generate-invite-codes` | 未列入 | 为存量用户生成邀请码；`--force` 时全员重算 |
| `inspire` | 未列入 | 在 `routes/console.php` 中定义，输出一条励志语录（Laravel 示例用） |

---

## 相关文件

- 命令类：`app/Console/Commands/`
- 调度注册：`routes/console.php`
- Cron 入口：服务器需配置 `* * * * * cd /path && php artisan schedule:run`（每分钟）
