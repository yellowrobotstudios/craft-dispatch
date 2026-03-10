# Tasks: Email Management Plugin

**Feature**: email-management
**Generated**: 2026-03-09
**Total Tasks**: 28

## User Story Mapping

| Story | Spec Scenario | Description | Tasks |
|-------|---------------|-------------|-------|
| US1   | Scenario 1    | Content editor edits email templates in CP | T007–T013 |
| US2   | Scenario 3+4  | Developer registers hooks via config file | T014–T018 |
| US3   | Scenario 5    | System sends email when hook fires | T019–T022 |
| US4   | Scenario 2    | Content editor previews email with data | T023–T025 |
| US5   | —             | Email logging and audit trail (FR-19, FR-20) | T026–T028 |

---

## Phase 1: Setup

- [x] T001 Create `composer.json` with package `yellowrobot/craft-dispatch`, require `craftcms/cms: ^5.0`, autoload `yellowrobot\craftdispatch\` from `src/`, and plugin extras (handle, name, class) in `composer.json`
- [x] T002 Create main plugin class in `src/CraftDispatch.php` — extend `craft\base\Plugin`, set `$hasCpSection = true`, `$schemaVersion = '1.0.0'`, implement `getCpNavItem()` with subnav for Templates and Logs, register component services (hook, email, log)
- [x] T003 Create `src/models/Settings.php` — plugin settings model with optional `fromEmail` and `fromName` overrides, with validation rules
- [x] T004 Create install migration in `src/migrations/Install.php` — create `craftdispatch_templates` table (id as PK + FK to elements, handle unique varchar, subject varchar, htmlBody text, textBody text nullable, dateCreated, dateUpdated, uid) and `craftdispatch_logs` table (id auto-increment, templateHandle varchar, recipient varchar, subject varchar, status varchar, errorMessage text nullable, dateSent datetime, dateCreated, uid) with indexes per data-model.md

## Phase 2: Foundational

- [x] T005 Create `src/elements/db/EmailTemplateQuery.php` — extend `craft\elements\db\ElementQuery`, add `$handle` and `$subject` public properties, implement `beforePrepare()` to join `craftdispatch_templates` table and select custom columns, support `Db::parseParam()` filtering on handle
- [x] T006 Create `src/elements/EmailTemplate.php` — extend `craft\base\Element`, define `$handle`, `$subject`, `$htmlBody`, `$textBody` properties, implement `displayName()`, `hasTitles()`, `find()` returning `EmailTemplateQuery`, `defineSources()`, `defineTableAttributes()`, `defineRules()` (handle required+unique, subject required, htmlBody required), `afterSave()` using `Db::upsert()` to persist custom fields to `craftdispatch_templates`, and register element type in plugin `init()`

## Phase 3: US1 — Template Management CP

> **Goal**: Content editors can create, list, edit, and delete email templates in the control panel.
> **Test**: Navigate to Email section in CP, create a template with handle/subject/body, verify it appears in the listing, edit it, verify changes persist.

- [x] T007 [US1] Register CP URL rules in `src/CraftDispatch.php` `init()` — listen to `UrlManager::EVENT_REGISTER_CP_URL_RULES` and register routes: `craft-dispatch` → `craft-dispatch/templates/index`, `craft-dispatch/new` → `craft-dispatch/templates/edit`, `craft-dispatch/edit/<elementId:\d+>` → `craft-dispatch/templates/edit`
- [x] T008 [US1] Create `src/controllers/TemplatesController.php` — implement `actionIndex()` rendering the template index page, `actionEdit(int $elementId = null)` loading or creating an EmailTemplate element and rendering the edit form, `actionSave()` populating element from POST data and saving via `Craft::$app->getElements()->saveElement()`
- [x] T009 [P] [US1] Create CP template `src/templates/templates/_index.twig` — use Craft's `{% extends '_layouts/elementindex' %}` with `elementType` set to the EmailTemplate class, define table columns (title, handle, subject, dateUpdated)
- [x] T010 [P] [US1] Create CP template `src/templates/templates/_edit.twig` — extend `_layouts/element` or `_layouts/cp`, include form fields: `handle` (text input), `subject` (text input), `htmlBody` (textarea or code editor), `textBody` (textarea), wire up Craft's standard element save form with CSRF and action input
- [x] T011 [US1] Add `defineSortOptions()` and `defineSearchableAttributes()` to `src/elements/EmailTemplate.php` — sort by title, handle, dateCreated, dateUpdated; searchable on title, handle, subject
- [x] T012 [US1] Add `defineActions()` to `src/elements/EmailTemplate.php` — support Delete action (`craft\elements\actions\Delete`) from the element index
- [x] T013 [US1] Implement `getFieldLayout()` or inline field rendering in the element editor so the edit page displays handle, subject, htmlBody, textBody fields using Craft's native CP field macros

## Phase 4: US2 — Hook Configuration System

> **Goal**: Developers define email hooks in `config/craft-dispatch.php` with closures; hooks auto-register and create default templates.
> **Test**: Add a hook entry to config file, verify the template handle appears in CP listing, verify no errors in Craft log.

- [x] T014 [US2] Create `src/models/EmailHook.php` — implement fluent builder: static `create(string $handle)` factory, `event(string $class, string $eventName)`, `transformer(callable $fn)`, `to(callable $fn)`, `when(callable $fn)` methods, plus `validate(): bool` checking that handle, eventClass, eventName, transformer, and recipientResolver are all set
- [x] T015 [US2] Create `src/services/HookService.php` — implement `getHooks(): array` that loads config via `Craft::$app->config->getConfigFromFile('craft-dispatch')`, extracts the `hooks` key, validates each `EmailHook` instance, logs and skips invalid hooks
- [x] T016 [US2] Implement event registration in `src/services/HookService.php` — add `registerListeners()` method that iterates validated hooks and calls `Event::on($hook->eventClass, $hook->eventName, $handler)` for each; the handler closure should be a placeholder that will be completed in US3
- [x] T017 [US2] Implement auto-creation of default templates in `src/services/HookService.php` — add `ensureTemplatesExist()` method that queries `EmailTemplate::find()->handle($hook->handle)->one()` for each hook, and if missing, creates a new `EmailTemplate` element with default subject/body content and saves it
- [x] T018 [US2] Wire `HookService` into `src/CraftDispatch.php` `init()` — call `registerListeners()` and `ensureTemplatesExist()` inside `Craft::$app->onInit()` callback to defer until Craft is fully booted

## Phase 5: US3 — Email Sending & Queue

> **Goal**: When a hooked Craft event fires, the system renders the template and sends the email via Craft's queue.
> **Test**: Trigger a hooked event (e.g., save a user/entry), verify a queue job is created, run the queue, verify email is sent via Craft's mailer.

- [x] T019 [US3] Create `src/services/EmailService.php` — implement `renderAndSend(string $templateHandle, array $recipients, array $variables): bool` that loads the EmailTemplate by handle, renders subject and htmlBody/textBody via `Craft::$app->getView()->renderString()` with the provided variables, composes a `craft\mail\Message`, sets from address (plugin settings or Craft default), and sends via `Craft::$app->getMailer()->send()`
- [x] T020 [US3] Create `src/jobs/SendEmailJob.php` — extend `craft\queue\BaseJob`, define serializable properties (`templateHandle`, `recipients` array, `variables` array), implement `execute($queue)` that calls `EmailService::renderAndSend()`, implement `defaultDescription()` returning a human-readable label
- [x] T021 [US3] Complete the event handler in `src/services/HookService.php` — in the `registerListeners()` handler closure: check `when()` guard (skip if false), execute `transformer()` to get variables array, execute `to()` to get recipients, wrap transformer/to in try/catch (log exception and return on failure), push a `SendEmailJob` via `Queue::push()`
- [x] T022 [US3] Add error handling in `src/services/EmailService.php` — wrap mailer `send()` in try/catch, log failures with full context (`templateHandle`, recipient, error message) via `Craft::error()`, return false on failure

## Phase 6: US4 — Template Preview

> **Goal**: Content editors can preview an email template with sample data without sending.
> **Test**: Edit a template, enter JSON variables, click preview, verify rendered HTML and plain text display correctly; enter invalid Twig, verify error message displays.

- [x] T023 [US4] Add `actionPreview()` to `src/controllers/TemplatesController.php` — accept POST JSON body with `subject`, `htmlBody`, `textBody`, `variables` (JSON object), render each via `Craft::$app->getView()->renderString()` in a try/catch, return JSON response with `success`, `subject`, `html`, `text` on success or `success: false` + `error` message on Twig exception
- [x] T024 [US4] Add preview pane to `src/templates/templates/_edit.twig` — add element selector (type determined from hook's eventClass), a "Preview" button, an iframe or div for rendered HTML output, a toggle between HTML and plain-text views
- [x] T025 [P] [US4] Create `src/templates/templates/_preview.js` (or inline JS in `_edit.twig`) — implement AJAX POST to the preview action endpoint with CSRF token, populate the preview pane with the response HTML, display Twig errors inline in a styled error block

## Phase 7: US5 — Email Logging

> **Goal**: Sent/failed emails are logged and viewable in the CP.
> **Test**: Trigger an email send, navigate to Dispatch → Logs, verify log entry with correct template handle, recipient, status, timestamp.

- [x] T026 [US5] Create `src/records/EmailLogRecord.php` — extend `craft\db\ActiveRecord`, set `tableName()` to `craftdispatch_logs`, define attribute labels
- [x] T027 [US5] Create `src/services/LogService.php` — implement `log(string $templateHandle, string $recipient, string $subject, string $status, ?string $errorMessage = null): void` that creates and saves an `EmailLogRecord`, and `getRecentLogs(int $limit = 50, int $offset = 0): array` for the CP index; wire into `EmailService::renderAndSend()` to log after each send attempt (success or failure)
- [x] T028 [US5] Create `src/controllers/LogsController.php` with `actionIndex()` and `actionDetail(int $id)`, and CP templates `src/templates/logs/_index.twig` (paginated table of logs: date, template, recipient, status) and `src/templates/logs/_detail.twig` (full log entry with error message if failed)

---

## Dependencies

```
T001 → T002 → T003, T004 (sequential setup)
T004, T005 → T006 (element type needs table + query)
T006 → T007–T013 (US1 needs element type)
T006 → T014–T018 (US2 needs element type for auto-creation)
T018 → T019–T022 (US3 needs hook listeners registered)
T008 → T023–T025 (US4 extends templates controller)
T019 → T026–T028 (US5 wires into email service)
```

### Story Completion Order

```
Setup (T001–T004)
  └→ Foundational (T005–T006)
       ├→ US1: Template CP (T007–T013)  ← MVP
       ├→ US2: Hook Config (T014–T018)  ← can parallel with US1 after T006
       │    └→ US3: Email Sending (T019–T022)
       │         └→ US5: Logging (T026–T028)
       └→ US4: Preview (T023–T025)  ← after US1 controller exists (T008)
```

## Parallel Execution Opportunities

**Within US1**: T009 and T010 are parallelizable (independent CP templates)
**US1 + US2**: Can run in parallel after foundational phase completes (T006)
**Within US4**: T025 (JS) is parallelizable with T024 (Twig template)
**US4 + US3**: Can run in parallel (US4 depends on US1, US3 depends on US2)

## Implementation Strategy

### MVP (Minimum Viable)

**US1 (Template Management)** alone is a useful increment — editors can manage email templates in the CP even without hooks. Ship this first and validate the element type works correctly before adding config hooks.

### Incremental Delivery

1. **MVP**: Setup + Foundational + US1 → templates editable in CP
2. **Core**: + US2 + US3 → config hooks fire and send emails
3. **Polish**: + US4 + US5 → preview and logging round out the experience
