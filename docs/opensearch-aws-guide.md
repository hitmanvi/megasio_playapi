# AWS OpenSearch 使用指南

本文档介绍如何从零开始使用 AWS OpenSearch，包括创建域、配置访问、以及在 PlayAPI 中接入。

---

## 一、创建 AWS OpenSearch 域

### 1.1 进入 OpenSearch 控制台

1. 登录 [AWS 控制台](https://console.aws.amazon.com/)
2. 搜索并进入 **OpenSearch Service**
3. 点击 **Create domain**

### 1.2 选择部署类型

| 选项 | 说明 | 适用场景 |
|------|------|----------|
| **Quick create** | 快速创建，使用默认配置 | 开发/测试 |
| **Standard create** | 完整配置，可自定义 | 生产环境 |

建议开发环境用 Quick create，生产环境用 Standard create。

### 1.3 Quick create 配置示例

- **Domain name**：`playapi-events`（自定义）
- **Deployment type**：Development and testing（单节点，成本低）
- **Version**：OpenSearch 2.x（推荐）
- **Data nodes**：默认 1 个节点
- **Storage**：默认 10 GB

点击 **Create** 等待约 10–15 分钟完成创建。

### 1.4 Standard create 配置要点

**Domain configuration**

- **Domain name**：如 `playapi-events-prod`
- **Engine version**：OpenSearch 2.11 或更高

**Data nodes**

- **Instance type**：`t3.small.search`（开发）/ `r6g.large.search`（生产）
- **Number of nodes**：开发 1 个，生产建议 2 个以上做高可用

**Storage**

- **EBS storage**：根据数据量选择，建议至少 20 GB

**Access policy**（重要）

- **Fine-grained access control**：**Enable**
- **Create master user**：**Enable**
  - **Master username**：如 `admin`
  - **Master password**：设置强密码并妥善保存

**Network**

- **VPC access**：若应用在 EC2/ECS/Lambda 等，选 VPC
- **Public access**：若从本地或公网访问，可启用（需配合安全组）

---

## 二、获取连接信息

### 2.1 获取 OpenSearch 端点

1. 在 OpenSearch 控制台进入你的域
2. 在 **Domain overview** 中找到 **OpenSearch domain endpoint**
3. 格式类似：`https://xxxxx-xxxxx.us-east-1.es.amazonaws.com`

> 注意：AWS OpenSearch 使用 **HTTPS**，端口为 **443**。

### 2.2 确认访问方式

| 访问方式 | 适用场景 | 说明 |
|----------|----------|------|
| **Public** | 本地开发、CI/CD | 需在访问策略中允许公网 IP |
| **VPC** | EC2、ECS、Lambda 同 VPC | 应用需与 OpenSearch 在同一 VPC |

---

## 三、配置访问策略（公网访问时）

若通过公网访问，需在域中配置访问策略：

1. 进入域 → **Security configuration** → **Edit**
2. 在 **Access policy** 中允许你的 IP 或 CIDR，例如：

```json
{
  "Effect": "Allow",
  "Principal": {
    "AWS": "*"
  },
  "Action": "es:*",
  "Resource": "arn:aws:es:us-east-1:123456789012:domain/playapi-events/*",
  "Condition": {
    "IpAddress": {
      "aws:SourceIp": ["YOUR_IP/32"]
    }
  }
}
```

将 `YOUR_IP` 替换为你的公网 IP，生产环境建议使用固定 IP 或 VPN。

---

## 四、PlayAPI 配置

### 4.1 环境变量

在 `.env` 中配置（**始终使用 IAM SigV4**；区域与其它 AWS 服务相同，使用 `AWS_DEFAULT_REGION`，未设置时默认 `us-east-1`）：

```env
# 启用 OpenSearch
OPENSEARCH_ENABLED=true

# AWS OpenSearch 端点（HTTPS，无需端口）
OPENSEARCH_HOSTS=https://xxxxx-xxxxx.us-east-1.es.amazonaws.com

AWS_DEFAULT_REGION=us-east-1
OPENSEARCH_SIGV4_SERVICE=es

# Index 前缀（用于区分环境）
OPENSEARCH_INDEX_PREFIX=playapi
```

### 4.2 多节点配置

若有多个端点，用逗号分隔：

```env
OPENSEARCH_HOSTS=https://node1.es.amazonaws.com,https://node2.es.amazonaws.com
```

---

## 五、测试连接

### 5.1 使用测试命令

```bash
php artisan test:opensearch-event
```

成功时输出类似：

```
测试事件: user_registered
目标 index: playapi-events-user-registered

✓ OpenSearch 连接正常

✓ 事件上传成功
文档 ID: xxxxx
```

### 5.2 常见错误

| 错误 | 可能原因 | 处理方式 |
|------|----------|----------|
| Connection refused / timeout | 网络不通或安全组限制 | 检查安全组、VPC、访问策略 |
| Connection timed out (port 9200) | opensearch-php 对 https 无端口 URL 错误使用 9200 | 已在 OpenSearchService 中自动补 `:443`，确保使用最新代码 |
| 401 Unauthorized / SigV4 失败 | IAM 无权限或凭证无效 | 核对调用身份是否有域访问策略与 IAM 策略允许 `es:HTTP*`；凭证是否为当前环境可用 |
| 403 Forbidden | 访问策略不允许当前 IP | 在访问策略中加入当前 IP |
| SSL certificate problem | 证书校验失败 | 确认使用 `https://` 且端点正确 |

### 5.3 curl 通但 ping 不通

若 `curl https://your-domain.es.amazonaws.com` 正常，但 `php artisan test:opensearch-event` 报连接失败，通常是 opensearch-php 对 HTTPS 端点错误使用了 9200 端口。OpenSearchService 已自动为 `https://` 且无端口的 URL 补上 `:443`。若仍有问题，可显式配置：

```env
OPENSEARCH_HOSTS=https://your-domain.es.amazonaws.com:443
```

---

## 六、VPC 内访问（生产推荐）

若应用部署在 EC2、ECS 或 Lambda，建议使用 VPC 访问：

1. **创建域时**选择 VPC 部署，并记录使用的子网和安全组
2. **应用所在资源**（EC2/ECS 等）需与 OpenSearch 在同一 VPC，或通过 VPC 对等/Transit Gateway 可达
3. **安全组**：在 OpenSearch 安全组的入站规则中，允许应用所在安全组访问 443 端口

```
Type: HTTPS
Port: 443
Source: 应用实例的安全组 ID 或 CIDR
```

4. **端点**：使用 VPC 内网端点，格式仍为 `https://xxxxx-xxxxx.us-east-1.es.amazonaws.com`

---

## 七、成本参考（us-east-1）

| 配置 | 月费用（约） |
|------|--------------|
| t3.small.search × 1，10 GB | ~$30 |
| r6g.large.search × 2，100 GB | ~$300 |
| Serverless（按用量） | 按实际使用计费 |

具体以 [AWS 定价](https://aws.amazon.com/opensearch-service/pricing/) 为准。

---

## 八、Index 模版与文档 ID

### 8.1 创建模版

首次使用或修改模版后，建议先创建 index 模版：

```bash
php artisan init:opensearch
```

仅创建指定模版：

```bash
php artisan init:opensearch --name=events
```

模版定义在 `config/opensearch.php` 的 `index_templates`，包含 `@timestamp`、`event_type`、`user_id` 等字段的 mapping。

### 8.2 指定文档 ID（幂等）

上传时指定 `--id` 可实现幂等：相同 ID 会覆盖，避免重复事件：

```bash
php artisan test:opensearch-event --id=user_registered_123_20250316
```

代码中调用：

```php
$openSearch->indexEvent('user_registered', $payload, $eventId);
```

建议 ID 格式：`{event_type}_{entity_id}_{timestamp}` 或业务唯一标识。

---

## 九、回填与统计

### 9.1 数据回填

将已有 users、deposits、withdraws 数据上传到 OpenSearch：

```bash
# 回填全部
php artisan test:opensearch-backfill

# 仅回填指定类型
php artisan test:opensearch-backfill --users
php artisan test:opensearch-backfill --deposits
php artisan test:opensearch-backfill --withdraws

# 指定每批数量，先创建模版
php artisan test:opensearch-backfill --chunk=200 --create-templates
```

> 用户充提汇总等统计接口应在后台系统中实现，PlayAPI 仅负责事件上报。

---

## 十、快速检查清单

- [ ] 已创建 OpenSearch 域并处于 Active 状态
- [ ] 已执行 `php artisan init:opensearch` 创建模版
- [ ] AWS 域访问策略与 IAM 允许当前调用身份访问（或使用本地无认证集群开发）
- [ ] 已获取正确的 Domain endpoint（HTTPS）
- [ ] 访问策略允许当前 IP（公网访问时）
- [ ] 安全组允许 443 入站（VPC 访问时）
- [ ] `.env` 中已配置 OPENSEARCH_* 变量
- [ ] `php artisan test:opensearch-event` 执行成功
- [ ] 需历史统计时，执行 `php artisan test:opensearch-backfill` 回填数据
