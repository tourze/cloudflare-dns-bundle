# CloudFlare DNS Bundle 命令行工具

## 域名同步命令 (SyncDomainsCommand)

该命令用于从CloudFlare同步域名到本地数据库，包括域名的Zone ID、状态、过期时间等信息。

### 用法

```bash
# 基本用法
bin/console cloudflare:sync-domains <iamKeyId> [<accountId>]

# 只同步特定域名
bin/console cloudflare:sync-domains <iamKeyId> [<accountId>] --domain=example.com

# 验证模式（不实际保存）
bin/console cloudflare:sync-domains <iamKeyId> [<accountId>] --dry-run

# 强制模式（不询问确认）
bin/console cloudflare:sync-domains <iamKeyId> [<accountId>] --force

# 尝试查找缺失的Zone ID
bin/console cloudflare:sync-domains <iamKeyId> [<accountId>] --lookup-zone-id
```

### 参数

- `iamKeyId`: 必需，IAM密钥ID
- `accountId`: 可选，CloudFlare账户ID。如果在IAM Key中已设置，则可省略

### 选项

- `--domain`, `-d`: 可选，只同步指定名称的域名
- `--dry-run`: 可选，仅验证而不实际同步
- `--force`, `-f`: 可选，强制同步，不进行确认
- `--lookup-zone-id`, `-z`: 可选，尝试查找缺失的Zone ID（需要额外API调用）

### 同步内容

命令会同步以下域名信息：
- 域名名称（name）
- Zone ID（很重要，用于后续DNS记录操作）
- Account ID
- 域名状态（status或last_known_status）
- 过期时间（expires_at）
- 锁定时间（locked_until或cor_locked_until）
- 自动续费设置（auto_renew）

### Zone ID处理策略

命令在处理Zone ID时采用以下策略：
1. 首先尝试从API返回的`id`字段获取
2. 如果没有`id`字段，尝试使用`registry_object_id`作为临时标识
3. 如果指定了`--lookup-zone-id`选项，会尝试通过额外API调用查找
4. 如果所有方法都失败，会保留现有Zone ID并显示警告

### 示例

同步IAM Key ID为1的所有域名(使用IAM Key中的Account ID):
```bash
bin/console cloudflare:sync-domains 1
```

同步IAM Key ID为1的所有域名(指定Account ID):
```bash
bin/console cloudflare:sync-domains 1 abcd1234
```

只同步特定域名:
```bash
bin/console cloudflare:sync-domains 1 --domain=example.com
```

验证模式:
```bash
bin/console cloudflare:sync-domains 1 --dry-run
```

尝试查找缺失的Zone ID:
```bash
bin/console cloudflare:sync-domains 1 --lookup-zone-id
```

### 注意事项

1. 确保IAM Key是有效的并且有权限访问CloudFlare API
2. 如果未在IAM Key中设置Account ID，则需要在命令行中提供
3. 同步过程会更新现有域名信息或创建新域名记录
4. Zone ID是Cloudflare的域名唯一标识符，对于后续DNS记录操作非常重要
5. 同步过程会显示当前Zone ID和新Zone ID的对比，方便查看变更
6. 命令支持Cloudflare不同API的返回格式（DNS API和域名注册API）
7. 如果需要操作DNS记录，必须获取正确的Zone ID，可能需要额外的API调用 