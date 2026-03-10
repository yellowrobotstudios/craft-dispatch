# Data Model: Email Management Plugin

## Entity Relationship Diagram

```
┌─────────────────────────┐
│    elements (Craft)     │  Craft's built-in elements table
│─────────────────────────│
│ id (PK)                 │
│ type                    │
│ enabled                 │
│ dateCreated             │
│ dateUpdated             │
│ ...                     │
└────────┬────────────────┘
         │ 1:1
         ▼
┌─────────────────────────┐       ┌─────────────────────────┐
│  craftdispatch_templates   │       │   craftdispatch_logs       │
│─────────────────────────│       │─────────────────────────│
│ id (PK, FK→elements)   │       │ id (PK, auto)           │
│ handle (unique)         │◄──────│ templateHandle          │
│ subject                 │       │ recipient               │
│ htmlBody                │       │ subject                 │
│ textBody (nullable)     │       │ status (enum)           │
│ dateCreated             │       │ errorMessage (nullable) │
│ dateUpdated             │       │ dateSent                │
│ uid                     │       │ dateCreated             │
└─────────────────────────┘       │ uid                     │
                                  └─────────────────────────┘
```

## Entity: EmailTemplate (Element)

Stored in `craftdispatch_templates`, linked to Craft's `elements` table.

| Field       | Type         | Constraints                    | Notes                                  |
|-------------|--------------|--------------------------------|----------------------------------------|
| id          | int          | PK, FK → elements.id, CASCADE | Craft element ID                       |
| handle      | varchar(255) | UNIQUE, NOT NULL               | Machine name, used in config hooks     |
| subject     | varchar(255) | NOT NULL                       | Twig-renderable subject line           |
| htmlBody    | text         | NOT NULL                       | Twig template for HTML email body      |
| textBody    | text         | NULLABLE                       | Optional plain-text Twig body          |
| dateCreated | datetime     | NOT NULL                       | Auto-populated by Craft                |
| dateUpdated | datetime     | NOT NULL                       | Auto-populated by Craft                |
| uid         | char(36)     | NOT NULL                       | Auto-populated by Craft                |

**Validation Rules**:
- `handle`: required, unique, alphanumeric + hyphens, max 255 chars
- `subject`: required, max 255 chars
- `htmlBody`: required
- `textBody`: optional

**Element Behaviors**:
- `title` (from elements table): used as display name in CP index
- `enabled`: controls whether the template is active (disabled = won't send)
- Searchable by title, handle, subject
- Supports soft delete via Craft's element trash

## Entity: EmailHook (Config-Defined, Not Stored)

Defined in `config/craft-dispatch.php`, exists only in memory at runtime.

| Property          | Type                  | Notes                                                  |
|-------------------|-----------------------|--------------------------------------------------------|
| handle            | string                | Matches a template handle                              |
| eventClass        | string (class-string) | Fully qualified class name (e.g., `User::class`)       |
| eventName         | string                | Event constant name (e.g., `Element::EVENT_AFTER_SAVE`)|
| transformer       | Closure               | `fn($event) => ['key' => 'value']`                     |
| recipientResolver | Closure               | `fn($event) => 'email@example.com'` or array           |
| condition         | Closure (optional)    | `fn($event) => bool` — guard clause, skip if false     |

**Fluent Builder API** (`EmailHook::create()`):
- `->event(string $class, string $eventName)`
- `->transformer(callable $fn)`
- `->to(callable $fn)`
- `->when(callable $fn)` (optional condition/guard)

## Entity: EmailLog (ActiveRecord)

Stored in `craftdispatch_logs`. Write-heavy audit table, not an element.

| Field        | Type         | Constraints        | Notes                         |
|--------------|--------------|--------------------|-------------------------------|
| id           | int          | PK, AUTO_INCREMENT | Standard auto-increment       |
| templateHandle| varchar(255)| NOT NULL           | Which template was used       |
| recipient    | varchar(255) | NOT NULL           | Recipient email address       |
| subject      | varchar(255) | NOT NULL           | Rendered subject line         |
| status       | enum         | NOT NULL           | `sent`, `failed`, `queued`    |
| errorMessage | text         | NULLABLE           | Error details if status=failed|
| dateSent     | datetime     | NOT NULL           | When the send was attempted   |
| dateCreated  | datetime     | NOT NULL           | Auto-populated by Craft       |
| uid          | char(36)     | NOT NULL           | Auto-populated by Craft       |

**Status Transitions**:
```
queued → sent
queued → failed
```

## Migration Plan

### Install Migration (`Install.php`)

Creates both tables with foreign keys:
1. `craftdispatch_templates` — FK to `elements.id` with CASCADE delete
2. `craftdispatch_logs` — standalone table, indexed on `templateHandle` and `dateSent`

### Indexes

- `craftdispatch_templates`: unique index on `handle`
- `craftdispatch_logs`: index on `templateHandle`, index on `dateSent`, index on `status`
