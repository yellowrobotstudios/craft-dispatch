# Contract: Control Panel Routes & Actions

## CP Routes

| Route                                    | Controller Action              | Description                  |
|------------------------------------------|-------------------------------|------------------------------|
| `GET craft-dispatch`                        | `templates/index`             | Template listing (element index) |
| `GET craft-dispatch/templates/new`          | `templates/edit`              | New template form            |
| `GET craft-dispatch/templates/<id:\d+>`     | `templates/edit`              | Edit existing template       |
| `POST craft-dispatch/templates/save`        | `templates/save`              | Save template (create/update)|
| `POST craft-dispatch/templates/delete`      | `templates/delete`            | Delete template              |
| `POST craft-dispatch/templates/preview`     | `templates/preview`           | Render preview (AJAX)        |
| `GET craft-dispatch/logs`                   | `logs/index`                  | Email log listing            |
| `GET craft-dispatch/logs/<id:\d+>`          | `logs/detail`                 | Log entry detail view        |

## Preview Action Contract

### Request

`POST /admin/craft-dispatch/templates/preview`

```
Content-Type: application/json
X-CSRF-Token: {token}

{
    "subject": "Welcome, {{ name }}!",
    "htmlBody": "<h1>Hello {{ name }}</h1><p>Your email is {{ email }}</p>",
    "textBody": "Hello {{ name }}. Your email is {{ email }}.",
    "variables": {
        "name": "Jane Smith",
        "email": "jane@example.com"
    }
}
```

### Response (200)

```json
{
    "success": true,
    "subject": "Welcome, Jane Smith!",
    "html": "<h1>Hello Jane Smith</h1><p>Your email is jane@example.com</p>",
    "text": "Hello Jane Smith. Your email is jane@example.com."
}
```

### Response (400 — Twig Error)

```json
{
    "success": false,
    "error": "Twig syntax error on line 3: Unexpected token..."
}
```

## CP Sidebar Navigation

```
Dispatch  (plugin icon)
├── Templates
└── Logs
```
