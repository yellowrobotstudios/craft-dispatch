# Quickstart: Hook-Template Contract

## Integration Scenarios

### Scenario 1: Fresh Install
1. Developer adds hooks to `config/craft-dispatch.php`
2. Developer runs `craft dispatch/sync`
3. Templates are created in DB with `enabled = false`
4. Admin opens Dispatch CP section, sees templates marked "Needs Content"
5. Admin authors content, enables each template
6. Hooks fire events, templates render and send

### Scenario 2: Adding a New Hook
1. Developer adds `EmailHook::create('new-notification')` to config
2. CP banner appears: "1 new hook needs a template"
3. Developer (or admin) runs `craft dispatch/sync`
4. New template appears in CP as disabled draft
5. Admin authors content and enables it

### Scenario 3: Removing a Hook
1. Developer removes a hook from config
2. CP shows the template as "Orphaned" (orange status)
3. No emails send for the removed hook (no matching listener)
4. Admin can delete the orphaned template or keep it for reference

### Scenario 4: Renaming a Hook Handle
1. Developer changes handle from `old-name` to `new-name`
2. CP shows `old-name` template as "Orphaned"
3. `craft dispatch/sync` creates `new-name` template (disabled, empty)
4. Admin copies content from orphaned template to new one
5. Admin enables new template, deletes orphan

### Scenario 5: Email Fails Due to Missing Template
1. Hook fires but no template exists (or template is disabled)
2. System logs to `craftdispatch_logs` with status `failed`, error message describes the cause
3. Admin sees the failure in Dispatch > Logs CP section

### Scenario 6: Connected Template Delete Attempt
1. Admin tries to delete a template that has an active hook
2. System prevents deletion — template is protected
3. Admin can disable the template instead (stops sending without losing content)

---

## Test Plan

### Unit Tests

- [ ] **HookRegistry**: `getRegisteredHandles()` returns all handles from config
- [ ] **HookRegistry**: `hasHandle('existing')` returns true, `hasHandle('missing')` returns false
- [ ] **EmailTemplate::getHookStatus()**: Returns `'connected'` when handle matches a registered hook
- [ ] **EmailTemplate::getHookStatus()**: Returns `'orphaned'` when handle has no matching hook
- [ ] **EmailTemplate::canDelete()**: Returns `false` when hook status is `'connected'`
- [ ] **EmailTemplate::canDelete()**: Returns `true` when hook status is `'orphaned'`

### Integration Tests

- [ ] **Sync command creates templates**: Given 3 hooks in config and 0 templates in DB, running `dispatch/sync` creates 3 templates with `enabled = false`
- [ ] **Sync command is idempotent**: Running `dispatch/sync` twice with the same config produces the same result (no duplicates)
- [ ] **Sync command handles soft-deleted templates**: If a template was soft-deleted, sync creates a new one (uses `->status(null)` query)
- [ ] **Orphan detection**: Given a template with handle `old-hook` and no matching hook in config, the template reports status `'orphaned'`
- [ ] **Send failure logging**: When a hook fires with no matching template, a log record is created with status `'failed'` and a descriptive error
- [ ] **Send failure logging (disabled template)**: When a hook fires and the matching template is disabled, a log record is created with status `'failed'`

### CP/Functional Tests

- [ ] **Template index shows status column**: Each template displays "Connected" (green) or "Orphaned" (orange)
- [ ] **Sync banner appears**: When hooks exist without templates, CP shows a notice with count
- [ ] **Delete action availability**: Connected templates have delete action grayed out; orphaned templates can be deleted
- [ ] **Console command output**: `craft dispatch/sync` outputs created/skipped/orphaned template counts

### Edge Cases

- [ ] **No hooks in config**: Sync command completes without error, all existing templates show as orphaned
- [ ] **Empty DB, many hooks**: All templates created correctly in a single sync run
- [ ] **Hook fires during sync**: Event handler gracefully handles missing template (logs failure, doesn't crash)
- [ ] **Duplicate handles in config**: System detects and warns (or errors) — handles must be unique
