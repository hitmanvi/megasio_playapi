# 领域事件与监听器

本文整理 `app/Events` 与 `app/Listeners` 的对应关系、业务含义，以及事件在业务代码中的**触发位置**。

## 注册方式

- 使用 **Laravel 11 监听器自动发现**：`App\Listeners` 下类的 `handle(具体Event $event)` 形参类型即绑定关系，**勿在** `Event::listen` 中重复注册，否则监听器会执行两次（见 `AppServiceProvider` 注释）。
- 当前**所有**项目内监听器均实现 `ShouldQueue`：通过队列异步执行；`QUEUE_CONNECTION=sync` 时则同步执行。
- 以下监听器设置 `public bool $afterCommit = true`：在**数据库事务提交后**再入队，避免主键/数据未落库时读不到：  
  `SendRegistrationEvent`、`SendLoginEvent`、`SendVipLevelUpgradeEvent`。

---

## 事件类与触发位置

| 事件 | 主要载荷 | 触发位置（`event(new …)`） |
|------|----------|----------------------------|
| `UserRegistered` | `User $user`，`$deviceInfo` | `AuthService`：手机/邮箱注册、部分第三方注册成功 |
| `UserLoggedIn` | `User $user`，`$ipAddress`，`$userAgent` | `AuthService`：注册后联带一次、密码登录、部分第三方登录成功 |
| `UserMineVisited` | `User $user` | `UserController`：用户进入「我的 / mine」类页面时 |
| `DepositCreated` | `Deposit $deposit`，`$deviceInfo` | `DepositService`：创建充值单并发起支付后 |
| `DepositCompleted` | `Deposit $deposit` | `DepositService::finishDeposit`：支付成功、订单置为完成并加余额后 |
| `FirstDepositCompleted` | `Deposit $deposit` | 同上，当该用户**首笔**成功完成的充值时，与 `DepositCompleted` 同次流程中额外触发 |
| `WithdrawCompleted` | `Withdraw` | `WithdrawService`：提现成功完成时 |
| `OrderCompleted` | `Order $order` | `OrderService`：游戏注单完成时（与 Funky 等订单流相关） |
| `BalanceChanged` | `userId`，`Balance $balance`，变动额、操作、类型等 | `BalanceService::updateBalance`：任意可用/冻结余额成功更新后 |
| `VipLevelUpgraded` | `User $user`，`$oldLevel`，`$newLevel` | `UserVipService`：加经验后发生 VIP 等级提升时 |
| `BonusTaskCompleted` | `BonusTask $task` | **当前代码中未 `event` 发出**；类已存在，可视为预留 |

---

## 按事件：监听器与处理逻辑

### `UserRegistered`

| 监听器 | 作用 |
|--------|------|
| `SendRegistrationEvent` | Facebook CAPI `CompleteRegistration`；`CustomerIOService::createCustomer` 建用户并写营销侧信息。Kochava 等部分逻辑在代码中注释。 |
| `IndexUserRegisteredToOpenSearch` | 若 OpenSearch 开启，上报 `user_registered` 事件用于统计/检索。 |

### `UserLoggedIn`（一次登录会同时触发下列监听器，执行顺序以队列入队顺序为准，不应用以强依赖先后）

| 监听器 | 作用 |
|--------|------|
| `SendLoginEvent` | Customer.io：`syncEmailOnLogin`、`sign_in` 事件。 |
| `RecordUserLoginActivity` | 更新用户 `last_login_at`，写入 `UserActivity` 登录记录。 |
| `IndexUserLoggedInToOpenSearch` | 若 OpenSearch 开启，上报 `user_logged_in`。 |

### `UserMineVisited`

| 监听器 | 作用 |
|--------|------|
| `SendMineVisitEvent` | Customer.io 发送 `visit` 事件（mine 页面访问）。 |

### `DepositCreated`

| 监听器 | 作用 |
|--------|------|
| `SendDepositCreateEvent` | Facebook `InitiateCheckout`；Customer.io `initiate_purchase`。Kochava 片段注释中。 |

### `DepositCompleted`

| 监听器 | 作用 |
|--------|------|
| `SendDepositCompleteEvent` | Kochava `purchase`；Facebook `Purchase`；Customer.io `purchase`。 |
| `CreateDepositBonusTask` | `PromotionService::processDepositBonus`，按促销规则生成存款类 bonus task。 |
| `CreateRollover` | 为该笔充值创建一条 rollover（默认 1 倍流水需求；无其他 active 时激活）。 |
| `CreateInvitationDepositReward` | 若用户有被邀请关系，按累计充值与配置检查并创建邀请人侧「存款进阶」类奖励（实现见类内）。 |
| `IndexDepositCompletedToOpenSearch` | OpenSearch：`deposit_completed`。 |

### `FirstDepositCompleted`

与 `DepositCompleted` 在同一笔回调里按条件额外触发。

| 监听器 | 作用 |
|--------|------|
| `SendFirstDepositCompleteEvent` | Kochava `first_purchase`；Facebook `FirstDeposit`。 |
| `AddPaidTagOnFirstDeposit` | 为用户打上标签 `Paid`（首次充值标记）。 |
| `IndexFirstDepositCompletedToOpenSearch` | OpenSearch：`first_deposit_completed`。 |

### `WithdrawCompleted`

| 监听器 | 作用 |
|--------|------|
| `IndexWithdrawCompletedToOpenSearch` | OpenSearch：`withdraw_completed`。 |

### `OrderCompleted`

| 监听器 | 作用 |
|--------|------|
| `UpdateUserWager` | 默认货币 + 游戏为 slot 等可计佣类型时，将订单 `amount` 计入 `UserWagerService`（供邀请奖励日结等使用）。 |
| `AddVipExpOnOrderCompleted` | 按订单金额与货币计算 VIP 经验并写入。 |
| `UpdateRolloverProgress` | 按订单下注额推进当前货币下 active rollover 流水；完成后激活下一条 pending。 |
| `UpdateWeeklyCashbackOnOrderCompleted` | 订单符合周返条件时写入 weekly cashback 缓冲（供定时任务聚合）。 |
| `CheckBonusTaskDeplete` | 订单绑定 bonus task 且满足 depleted 条件时更新任务状态并推送前端（WebSocket）。 |
| `RecordUserRecentGame` | 派发 `RecordUserRecentGameJob`，更新用户最近游玩游戏与倍数等。 |

### `BalanceChanged`

| 监听器 | 作用 |
|--------|------|
| `NotifyBalanceChanged` | 向用户 WebSocket 推送 `balance.changed`（当前币种可用/冻结、变动信息等）。 |

### `VipLevelUpgraded`

| 监听器 | 作用 |
|--------|------|
| `NotifyVipLevelUpgraded` | 读 VIP 配置中的 `level_cash_bonus`，创建「VIP 升级」站内通知。 |
| `SendVipLevelUpgradeEvent` | Customer.io 更新属性 `vip`、发送 `vip` 事件。 |
| `CreateInvitationVipReward` | 若配置启用，为邀请关系创建邀请人侧 VIP 升级奖励记录。 |

---

## 按业务域索引（便于检索）

| 域 | 事件 | 监听器要点 |
|----|------|------------|
| **认证 / 登录审计** | `UserRegistered`、`UserLoggedIn` | 营销像素、Customer.io、`last_login_at`、UserActivity、OpenSearch |
| **页面行为** | `UserMineVisited` | Customer.io `visit` |
| **充值漏斗** | `DepositCreated`、`DepositCompleted`、`FirstDepositCompleted` | 像素、Kochava、Customer.io、促销 rollover、邀请存款奖励、Paid 标签、OpenSearch |
| **提现** | `WithdrawCompleted` | OpenSearch |
| **游戏下注** | `OrderCompleted` | wager、VIP 经验、rollover、周返缓冲、bonus task 耗尽、最近游玩 |
| **余额推送** | `BalanceChanged` | WebSocket 余额变更 |
| **VIP / 邀请** | `VipLevelUpgraded` | 站内通知、Customer.io、邀请 VIP 奖励 |

---

## 运维提示

- **队列**：生产环境需运行 `queue:work`，否则 `ShouldQueue` 监听器不会异步执行。
- **调试**：若曾手动 `Event::listen` 重复绑定，请参考仓库内既有说明文档排查「同一监听执行两次」问题。
- **OpenSearch**：各 `Index*ToOpenSearch` 监听器内会先判断 `OpenSearchService::isEnabled()`。
