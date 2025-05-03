# 工作流说明：Cloudflare DNS Bundle

本文档通过 Mermaid 流程图展示 Cloudflare DNS Bundle 的主要工作流。

## 1. 域名信息同步

```mermaid
flowchart TD
    Start([开始])
    ListDomains["从数据库获取所有域名"]
    ForEachDomain["遍历每个域名"]
    FetchCloudflare["从 Cloudflare 获取域名信息"]
    UpdateDB["更新数据库中的域名信息"]
    End([结束])

    Start --> ListDomains --> ForEachDomain
    ForEachDomain --> FetchCloudflare --> UpdateDB --> ForEachDomain
    ForEachDomain --> End
```

## 2. DNS 记录同步（Cloudflare -> 本地）

```mermaid
flowchart TD
    Start([开始])
    ListDomains["从数据库获取所有域名"]
    ForEachDomain["遍历每个域名"]
    ListRecords["从 Cloudflare 获取 DNS 记录"]
    ForEachRecord["遍历每条记录"]
    UpsertDB["写入/更新本地数据库"]
    End([结束])

    Start --> ListDomains --> ForEachDomain
    ForEachDomain --> ListRecords --> ForEachRecord
    ForEachRecord --> UpsertDB --> ForEachRecord
    ForEachRecord --> ForEachDomain
    ForEachDomain --> End
```

## 3. DNS 记录同步（本地 -> Cloudflare）

```mermaid
flowchart TD
    Start([开始])
    GetRecord["根据 ID 获取本地 DNS 记录"]
    IfHasRecordId{有 Cloudflare 记录ID?}
    SearchCloudflare["在 Cloudflare 搜索记录"]
    CreateCloudflare["在 Cloudflare 创建记录"]
    UpdateCloudflare["在 Cloudflare 更新记录"]
    End([结束])

    Start --> GetRecord --> IfHasRecordId
    IfHasRecordId -- 否 --> SearchCloudflare --> IfHasRecordId
    IfHasRecordId -- 否 --> CreateCloudflare --> UpdateCloudflare
    IfHasRecordId -- 是 --> UpdateCloudflare --> End
```

## 4. DNS 分析数据同步

```mermaid
flowchart TD
    Start([开始])
    ListDomains["从数据库获取所有域名"]
    ForEachDomain["遍历每个域名"]
    FetchAnalytics["从 Cloudflare 获取分析数据"]
    ForEachData["遍历每条分析数据"]
    SaveDB["写入数据库"]
    End([结束])

    Start --> ListDomains --> ForEachDomain
    ForEachDomain --> FetchAnalytics --> ForEachData
    ForEachData --> SaveDB --> ForEachData
    ForEachData --> ForEachDomain
    ForEachDomain --> End
```

## 说明

- 所有工作流均可通过命令行或服务方法触发。
- 每一步均有错误处理和日志记录。
- 支持批量操作以提升效率。
