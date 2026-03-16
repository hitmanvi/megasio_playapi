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

在 `.env` 中配置：

```env
# 启用 OpenSearch
OPENSEARCH_ENABLED=true

# AWS OpenSearch 端点（HTTPS，无需端口）
OPENSEARCH_HOSTS=https://xxxxx-xxxxx.us-east-1.es.amazonaws.com

# Master 用户名和密码（创建域时设置）
OPENSEARCH_USERNAME=admin
OPENSEARCH_PASSWORD=your_master_password

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
| 401 Unauthorized | 用户名或密码错误 | 核对 OPENSEARCH_USERNAME / OPENSEARCH_PASSWORD |
| 403 Forbidden | 访问策略不允许当前 IP | 在访问策略中加入当前 IP |
| SSL certificate problem | 证书校验失败 | 确认使用 `https://` 且端点正确 |

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

## 七、IAM 认证（可选）

除 Master 用户外，AWS OpenSearch 支持 IAM 身份认证。当前 PlayAPI 使用 Basic Auth（Master 用户），若需 IAM：

1. 安装 `aws/aws-sdk-php`
2. 在 `OpenSearchService` 的 `buildClient()` 中配置 SigV4 签名
3. 使用 IAM 角色或 Access Key 替代用户名密码

具体实现可参考 [opensearch-php AWS 文档](https://github.com/opensearch-project/opensearch-php#aws-opensearch-service)。

---

## 八、成本参考（us-east-1）

| 配置 | 月费用（约） |
|------|--------------|
| t3.small.search × 1，10 GB | ~$30 |
| r6g.large.search × 2，100 GB | ~$300 |
| Serverless（按用量） | 按实际使用计费 |

具体以 [AWS 定价](https://aws.amazon.com/opensearch-service/pricing/) 为准。

---

## 九、快速检查清单

- [ ] 已创建 OpenSearch 域并处于 Active 状态
- [ ] 已启用 Fine-grained access control 并创建 Master 用户
- [ ] 已获取正确的 Domain endpoint（HTTPS）
- [ ] 访问策略允许当前 IP（公网访问时）
- [ ] 安全组允许 443 入站（VPC 访问时）
- [ ] `.env` 中已配置 OPENSEARCH_* 变量
- [ ] `php artisan test:opensearch-event` 执行成功
