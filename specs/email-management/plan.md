# Implementation Plan: Email Management Plugin

**Feature**: email-management
**Created**: 2026-03-09
**Status**: Planning Complete

## Technical Context

- **Platform**: Craft CMS 5.x, PHP 8.2+
- **Namespace**: `yellowrobot\craftdispatch`
- **Composer package**: `yellowrobot/craft-dispatch`
- **Plugin handle**: `craft-dispatch`
- **Plugin class**: `yellowrobot\craftdispatch\CraftDispatch`
- **Config file**: `config/craft-dispatch.php`

### Key Craft APIs Used

| Concern              | Craft API                                          |
|----------------------|----------------------------------------------------|
| Data storage         | Custom Element Type + `Db::upsert()` in `afterSave()` |
| CP section           | `$hasCpSection = true` + `getCpNavItem()`          |
| CP routes            | `UrlManager::EVENT_REGISTER_CP_URL_RULES`          |
| Event listening      | `Event::on($class, $event, $handler)`              |
| Email sending        | `Craft::$app->getMailer()->send($message)`         |
| Queue jobs           | `craft\queue\BaseJob` + `Queue::push()`            |
| Twig rendering       | `Craft::$app->getView()->renderString()`           |
| Migrations           | `craft\db\Migration` (Install.php)                 |
| Config               | `Craft::$app->config->getConfigFromFile()`         |

### Dependencies

- `craftcms/cms: ^5.0` (sole dependency beyond PHP)

## Architecture Overview

```
src/
├── CraftDispatch.php                    # Main plugin class
├── models/
│   ├── EmailHook.php                 # Fluent builder for hook config
│   └── Settings.php                  # Plugin settings model
├── elements/
│   ├── EmailTemplate.php             # Custom element type
│   └── db/
│       └── EmailTemplateQuery.php    # Element query class
├── records/
│   └── EmailLogRecord.php            # ActiveRecord for logs table
├── services/
│   ├── HookService.php               # Parses config, registers event listeners
│   ├── EmailService.php              # Renders templates, sends emails
│   └── LogService.php                # Writes/queries log entries
├── controllers/
│   ├── TemplatesController.php       # CP CRUD + preview action
│   └── LogsController.php            # CP log viewer
├── jobs/
│   └── SendEmailJob.php              # Queue job for async sending
├── migrations/
│   └── Install.php                   # Creates tables on install
└── templates/                        # CP Twig templates (plugin UI)
    ├── templates/
    │   ├── _index.twig               # Template listing
    │   └── _edit.twig                # Template editor + preview
    └── logs/
        ├── _index.twig               # Log listing
        └── _detail.twig              # Log detail view
```

## Implementation Phases

### Phase 1: Plugin Scaffold & Database

**Goal**: Installable plugin with tables created.

1. Initialize Composer package (`composer.json` with `yellowrobot/craft-dispatch`)
2. Create `src/CraftDispatch.php` — main plugin class with `$hasCpSection = true`
3. Create `src/migrations/Install.php` — creates `craftdispatch_templates` and `craftdispatch_logs` tables
4. Create `src/models/Settings.php` — plugin settings (fromEmail, fromName overrides)
5. Verify: plugin installs, tables exist, CP nav item appears

### Phase 2: Email Template Element Type

**Goal**: CRUD email templates in the CP.

1. Create `src/elements/EmailTemplate.php` — custom element with handle, subject, htmlBody, textBody
2. Create `src/elements/db/EmailTemplateQuery.php` — query class with handle filter
3. Register element type in plugin `init()`
4. Create `src/controllers/TemplatesController.php` — index, edit, save, delete actions
5. Create CP templates: `_index.twig` (element index), `_edit.twig` (editor form)
6. Register CP URL rules
7. Verify: can create, list, edit, delete templates in CP

### Phase 3: Hook Configuration System

**Goal**: Developer-defined hooks in config file auto-register event listeners.

1. Create `src/models/EmailHook.php` — fluent builder with `create()`, `event()`, `transformer()`, `to()`, `when()`
2. Create `src/services/HookService.php` — loads config, validates hooks, registers `Event::on()` listeners
3. Wire HookService into plugin `init()` (deferred via `Craft::$app->onInit()`)
4. Auto-create default EmailTemplate records for hook handles that don't have CP templates yet
5. Verify: config hooks appear in CP, events fire listeners

### Phase 4: Email Sending & Queue

**Goal**: Hooked events trigger queued email sends.

1. Create `src/jobs/SendEmailJob.php` — receives templateHandle, recipient(s), variables; renders and sends
2. Create `src/services/EmailService.php` — renders subject + body via `renderString()`, composes `Message`, delegates to mailer
3. Wire event listeners (from HookService) to: run transformer → run recipient resolver → push SendEmailJob
4. Handle errors: transformer exceptions logged and skipped, send failures logged
5. Verify: triggering an event queues and sends an email

### Phase 5: Preview System

**Goal**: Preview templates with sample data in the CP.

1. Add `preview` action to `TemplatesController` — accepts subject, htmlBody, textBody, variables JSON; returns rendered output
2. Add preview pane to `_edit.twig` — JSON textarea for variables, iframe/div for rendered preview, refresh button
3. JS: AJAX POST to preview endpoint, display result
4. Handle Twig errors gracefully (return error message, don't crash)
5. Verify: preview renders correctly, errors display inline

### Phase 6: Email Logging

**Goal**: Audit trail of sent emails viewable in CP.

1. Create `src/records/EmailLogRecord.php` — ActiveRecord for `craftdispatch_logs`
2. Create `src/services/LogService.php` — create log entries on send success/failure
3. Wire into SendEmailJob — log after each send attempt
4. Create `src/controllers/LogsController.php` — index + detail actions
5. Create CP templates: `logs/_index.twig`, `logs/_detail.twig`
6. Add "Logs" to CP subnav
7. Verify: sent emails appear in logs with status and details

## Risk Assessment

| Risk                                    | Likelihood | Impact | Mitigation                                      |
|-----------------------------------------|------------|--------|--------------------------------------------------|
| Closures not serializable in queue jobs | High       | High   | Execute closures at event time, pass resolved data to job |
| Twig rendering exposes security risk    | Medium     | High   | Use `renderString()` with site template mode; consider sandboxing for user-provided content |
| Config validation errors crash site     | Medium     | High   | Wrap config loading in try/catch, log errors, skip invalid hooks |
| Element type complexity                 | Medium     | Medium | Follow Campaign plugin's pattern as reference implementation |

## Artifacts

| Artifact                          | Path                                               |
|-----------------------------------|----------------------------------------------------|
| Feature Spec                      | `specs/email-management/spec.md`                   |
| Research                          | `specs/email-management/research.md`               |
| Data Model                        | `specs/email-management/data-model.md`             |
| Config Contract                   | `specs/email-management/contracts/config-contract.md` |
| CP Routes Contract                | `specs/email-management/contracts/cp-routes.md`    |
| Quickstart & Test Plan            | `specs/email-management/quickstart.md`             |
| Requirements Checklist            | `specs/email-management/checklists/requirements.md`|
