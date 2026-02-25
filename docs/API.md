# PlayAPI 接口文档

## 通用说明

- 基础路径：`/api`
- 需要认证的接口需在请求头携带：`Authorization: Bearer {token}`
- 响应格式：`{ "code": 0, "errmsg": "Success", "data": {...} }`
- 错误时 `code` 非 0，`errmsg` 为错误描述

---

## Weekly Cashback 周返现

### 获取可领取的 Cashback

获取用户上周可领取的 cashback（单个）。无记录返回 `data: null`。同时会将非上周的 claimable 记录标记为过期。

```
GET /api/weekly-cashbacks/claimable
```

**认证**：需要

**响应示例**：
```json
{
  "code": 0,
  "errmsg": "Success",
  "data": {
    "no": "01ARZ3NDEKTSV4RRFFQ69G5FAV",
    "period": 202605,
    "currency": "USD",
    "wager": 1000.00,
    "payout": 900.00,
    "status": "claimable",
    "rate": 0.05,
    "amount": 50.00,
    "claimed_at": null,
    "created_at": "2026-02-01 10:00:00",
    "updated_at": "2026-02-05 10:00:00"
  }
}
```

无记录时 `data` 为 `null`。

---

### 获取 Cashback 详情

通过 `no`（ULID）获取指定 cashback 详情。

```
GET /api/weekly-cashbacks/{no}
```

**认证**：需要

**路径参数**：
| 参数 | 类型 | 说明 |
|------|------|------|
| no | string | Cashback 标识（26 位 ULID） |

**错误码**：
| code | 说明 |
|------|------|
| 4010 | Weekly cashback not found |

---

### 领取 Cashback

领取指定 cashback，金额将入账到用户余额。

```
POST /api/weekly-cashbacks/{no}/claim
```

**认证**：需要

**路径参数**：
| 参数 | 类型 | 说明 |
|------|------|------|
| no | string | Cashback 标识（26 位 ULID） |

**成功响应示例**：
```json
{
  "code": 0,
  "errmsg": "Success",
  "data": {
    "no": "01ARZ3NDEKTSV4RRFFQ69G5FAV",
    "period": 202605,
    "currency": "USD",
    "wager": 1000.00,
    "payout": 900.00,
    "status": "claimed",
    "rate": 0.05,
    "amount": 50.00,
    "claimed_at": "2026-02-05 12:00:00",
    "created_at": "2026-02-01 10:00:00",
    "updated_at": "2026-02-05 12:00:00",
    "claim_amount": 50.00,
    "currency": "USD"
  }
}
```

**错误码**：
| code | 说明 |
|------|------|
| 4010 | Weekly cashback not found |
| 4011 | Weekly cashback is not claimable |
| 4012 | No amount to claim |

---

## 测试命令

### 创建 Weekly Cashback 测试数据

为指定用户创建当前周期、上一周期、上上一周期的 weekly cashback 记录。

```bash
php artisan test:create-weekly-cashback {user_id} [--currency=USD] [--wager=1000] [--rate=0.05]
```

**参数**：
| 参数 | 必填 | 默认值 | 说明 |
|------|------|--------|------|
| user_id | 是 | - | 用户 ID |
| --currency | 否 | USD | 货币类型 |
| --wager | 否 | 1000 | 投注额 |
| --rate | 否 | 0.05 | 返现比例（0~1） |

**创建的周期**：
- 当前周期：`status = active`
- 上一周期：`status = claimable`（可领取）
- 上上一周期：`status = expired`

**示例**：
```bash
php artisan test:create-weekly-cashback 1
php artisan test:create-weekly-cashback 1 --currency=INR --wager=5000 --rate=0.08
```

---

## 定时任务

### 刷入 Weekly Cashback 缓冲

将 Redis 中的 weekly cashback 缓冲数据刷入数据库，建议每分钟执行。

```bash
php artisan weekly-cashback:flush-buffer
```
