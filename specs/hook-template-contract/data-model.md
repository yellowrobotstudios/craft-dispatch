# Data Model: Hook-Template Contract

## Entities

### EmailHook (config, runtime only)

| Field | Type | Description |
|-------|------|-------------|
| handle | string | Unique identifier, matches template handle |
| eventClass | string | Craft/Yii event class |
| eventName | string | Event name constant |
| transformer | Closure | Converts event to template variables |
| recipientResolver | Closure | Returns email addresses |
| condition | Closure? | Guard condition |
| sendMode | string | 'list' or 'individual' |
| channels | array | ['email', 'slack', 'webhook'] |

**Lifecycle**: Exists only in memory, built from `config/craft-dispatch.php` on each request.

### EmailTemplate (DB element)

| Field | Type | Description |
|-------|------|-------------|
| id | int | FK to elements.id |
| handle | string | Unique, matches hook handle |
| subject | string | Email subject (Twig) |
| htmlBody | text | Email body HTML (Twig) |
| textBody | text? | Plain text version |
| enabled | bool | Whether this template sends (inherited from Element) |
| title | string | Display name in CP (inherited from Element) |

**Table**: `craftdispatch_templates`

**No schema changes needed** — the existing table supports all requirements.

### EmailLogRecord (DB)

| Field | Type | Description |
|-------|------|-------------|
| id | int | PK |
| templateHandle | string | Hook handle that triggered the send |
| recipient | string | Email address(es) |
| subject | string | Rendered subject |
| status | string | 'queued', 'sent', 'failed' |
| errorMessage | text? | Error details if failed |
| dateSent | datetime | When attempted |

**Table**: `craftdispatch_logs`

**No schema changes needed.**

## Relationships

```
EmailHook (config)  ──── handle ────  EmailTemplate (DB)
     │                                      │
     │ fires event                          │ content used by
     ▼                                      ▼
 _handleEvent()  ──── templateHandle ──── renderAndSend()
                                            │
                                            ▼
                                      EmailLogRecord
```

The handle string is the join key. The relationship is:
- **1:1** — each hook expects exactly one template with a matching handle
- **Validated at runtime** — no FK constraint, checked by code

## State Transitions

### Template Lifecycle

```
[Hook added to config]
    │
    ▼
MISSING ──(sync command)──► DRAFT (enabled=false, placeholder content)
    │                           │
    │                      (admin authors content + enables)
    │                           │
    │                           ▼
    │                       ACTIVE (enabled=true, real content)
    │                           │
    │                      (hook removed from config)
    │                           │
    │                           ▼
    │                       ORPHANED (no matching hook)
    │                           │
    │                      (admin deletes)
    │                           │
    │                           ▼
    └──────────────────────  DELETED
```

### Hook Status (computed, not stored)

| Template exists? | Hook exists? | Status |
|:---:|:---:|:---|
| No | Yes | **Missing** — needs sync |
| Yes (disabled) | Yes | **Draft** — needs content |
| Yes (enabled) | Yes | **Connected** — active |
| Yes | No | **Orphaned** — hook removed |
