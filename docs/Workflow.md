# Workflow: Cloudflare DNS Bundle

This document illustrates the main workflows of the Cloudflare DNS Bundle using Mermaid diagrams.

## 1. Domain Info Sync

```mermaid
flowchart TD
    Start([Start])
    ListDomains["List all domains from DB"]
    ForEachDomain["For each domain"]
    FetchCloudflare["Fetch domain info from Cloudflare"]
    UpdateDB["Update domain info in DB"]
    End([End])

    Start --> ListDomains --> ForEachDomain
    ForEachDomain --> FetchCloudflare --> UpdateDB --> ForEachDomain
    ForEachDomain --> End
```

## 2. DNS Record Sync (Cloudflare -> Local)

```mermaid
flowchart TD
    Start([Start])
    ListDomains["List all domains from DB"]
    ForEachDomain["For each domain"]
    ListRecords["List DNS records from Cloudflare"]
    ForEachRecord["For each record"]
    UpsertDB["Upsert record in DB"]
    End([End])

    Start --> ListDomains --> ForEachDomain
    ForEachDomain --> ListRecords --> ForEachRecord
    ForEachRecord --> UpsertDB --> ForEachRecord
    ForEachRecord --> ForEachDomain
    ForEachDomain --> End
```

## 3. DNS Record Sync (Local -> Cloudflare)

```mermaid
flowchart TD
    Start([Start])
    GetRecord["Get local DNS record by ID"]
    IfHasRecordId{Has Cloudflare Record ID?}
    SearchCloudflare["Search record on Cloudflare"]
    CreateCloudflare["Create record on Cloudflare"]
    UpdateCloudflare["Update record on Cloudflare"]
    End([End])

    Start --> GetRecord --> IfHasRecordId
    IfHasRecordId -- No --> SearchCloudflare --> IfHasRecordId
    IfHasRecordId -- No --> CreateCloudflare --> UpdateCloudflare
    IfHasRecordId -- Yes --> UpdateCloudflare --> End
```

## 4. DNS Analytics Sync

```mermaid
flowchart TD
    Start([Start])
    ListDomains["List all domains from DB"]
    ForEachDomain["For each domain"]
    FetchAnalytics["Fetch analytics from Cloudflare"]
    ForEachData["For each analytics data"]
    SaveDB["Save analytics to DB"]
    End([End])

    Start --> ListDomains --> ForEachDomain
    ForEachDomain --> FetchAnalytics --> ForEachData
    ForEachData --> SaveDB --> ForEachData
    ForEachData --> ForEachDomain
    ForEachDomain --> End
```

## Notes

- All workflows are triggered via CLI commands or service methods.
- Error handling and logging are present at each step.
- Batch operations are supported for efficiency.
