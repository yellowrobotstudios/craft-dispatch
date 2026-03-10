# Tasks: Hook-Template Contract

**Input**: spec.md, research.md, data-model.md, quickstart.md
**Organization**: Tasks are grouped by user story to enable independent implementation and testing.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel (different files, no dependencies)
- **[Story]**: Which user story this task belongs to (e.g., US1, US2)

## User Story Mapping

| ID | Spec Scenario | Priority |
|----|---------------|----------|
| US1 | Developer adds a new hook (FR1 + FR2) | P1 |
| US2 | Admin views template list with status (FR3) | P1 |
| US3 | Email fails due to missing template (FR4) | P1 |
| US4 | Developer removes a hook — orphan visibility (FR3 + FR5) | P2 |
| US5 | Template protection — delete prevention (FR5) | P2 |

---

## Phase 1: Setup

**Purpose**: No new files needed — plugin structure already exists. This phase removes the side-effect sync behavior.

- [x] T001 Remove `ensureTemplatesExist()` call from `CraftDispatch::init()` in `src/CraftDispatch.php`

**Checkpoint**: Plugin loads without auto-creating templates on CP request.

---

## Phase 2: Foundational — Sync Console Command

**Purpose**: Replace side-effect sync with explicit `craft dispatch/sync` console command. BLOCKS US1 and US2.

- [x] T002 Create `src/console/controllers/SyncController.php` with `actionIndex()` that:
  - Gets all registered hook handles from `HookService::getRegisteredHandles()`
  - Queries existing template handles (including disabled, using `->status(null)`)
  - Creates missing templates with `enabled = false` and placeholder content
  - Reports: created count, skipped (already exists) count, orphaned count
  - Outputs summary to console (e.g., "Created 3 templates. 1 orphaned template detected.")

- [x] T003 Register the console controller in `CraftDispatch.php` via `EVENT_REGISTER_CONSOLE_COMMANDS` or by overriding `$controllerMap` for console context

- [x] T004 Update `ensureTemplatesExist()` in `src/services/HookService.php` to set `enabled = false` on new templates (currently sets `enabled = true`)

- [x] T005 Call sync logic from `afterInstall()` in `CraftDispatch.php` so templates are created on first install

**Checkpoint**: `craft dispatch/sync` creates templates for all configured hooks. Templates are disabled by default.

---

## Phase 3: User Story 1 — Sync Status Banner (Priority: P1) MVP

**Goal**: When hooks and templates are out of sync, admin sees a banner in the CP Dispatch section.

**Independent Test**: Add a hook to config without running sync. Open Dispatch in CP. Banner should say "1 new hook needs a template."

### Implementation

- [x] T006 [US1] Add `getSyncStatus()` method to `src/services/HookService.php` that returns `['missing' => [...handles], 'orphaned' => [...handles]]` by comparing registered handles against DB template handles

- [x] T007 [US1] Update `TemplatesController::actionIndex()` in `src/controllers/TemplatesController.php` to pass sync status to the template index view

- [x] T008 [US1] Add sync status banner to the CP template index view — show notice with counts (e.g., "2 hooks need templates. Run `craft dispatch/sync` to create them." and "1 orphaned template detected.")

**Checkpoint**: Admin opens Dispatch → Templates and immediately sees which hooks are missing templates and which templates are orphaned.

---

## Phase 4: User Story 2 — Hook Status Column (Priority: P1)

**Goal**: Each template row in the CP index shows its relationship status: Connected, Orphaned, or Needs Content (draft).

**Independent Test**: With connected + orphaned templates in DB, the index shows correct colored status for each.

### Implementation

- [x] T009 [US2] Update `getHookStatus()` in `src/elements/EmailTemplate.php` to return three states: `'connected'`, `'draft'` (connected but disabled), or `'orphaned'` instead of just two

- [x] T010 [US2] Update `tableAttributeHtml()` in `src/elements/EmailTemplate.php` for the `hookStatus` attribute to render three states:
  - Connected (green): enabled template with matching hook
  - Needs Content (blue/yellow): disabled template with matching hook
  - Orphaned (orange): no matching hook in config

**Checkpoint**: Template index clearly shows which templates need attention at a glance.

---

## Phase 5: User Story 3 — Send Failure Logging (Priority: P1)

**Goal**: When a hook fires and no matching enabled template exists, the failure is logged to `craftdispatch_logs` (visible in CP), not just to Craft's error log.

**Independent Test**: Fire a hook event with no template in DB. Check Dispatch > Logs — failure entry should appear with handle, error message, and intended recipients.

### Implementation

- [x] T011 [US3] Update `renderAndSend()` in `src/services/EmailService.php` to log a failure record via `LogService` when template is not found or is disabled, including handle, recipients, and descriptive error message

- [x] T012 [US3] Update `_handleEvent()` in `src/services/HookService.php` to catch and log failures when the queued job would fail due to missing template — consider checking template existence before queueing, and logging immediately if missing

**Checkpoint**: Every send failure appears in Dispatch > Logs. No silent failures.

---

## Phase 6: User Story 4 — Orphan Visibility (Priority: P2)

**Goal**: When a developer removes a hook from config, the template is clearly flagged as orphaned in the CP. Admin can decide what to do with it.

**Independent Test**: Remove a hook from config. Reload CP. Template shows "Orphaned" status. No emails send for it.

### Implementation

- [x] T013 [US4] Already covered by T009/T010 (status column) and T006/T008 (banner). Verify orphan detection works end-to-end: remove a hook handle from config, confirm template shows orange "Orphaned" status and banner counts it.

**Checkpoint**: Orphaned templates are immediately visible. No manual investigation needed.

---

## Phase 7: User Story 5 — Template Protection (Priority: P2)

**Goal**: Connected templates cannot be accidentally deleted. Orphaned templates can be cleaned up.

**Independent Test**: Try to delete a connected template — blocked. Try to delete an orphaned template — allowed.

### Implementation

- [x] T014 [US5] Verify `canDelete()` in `src/elements/EmailTemplate.php` correctly prevents deletion of connected templates and allows deletion of orphaned ones (already implemented — confirm behavior is correct)

- [x] T015 [US5] Add `canDuplicate()` returning `false` in `src/elements/EmailTemplate.php` — duplicating templates would create handle conflicts

- [x] T016 [US5] Ensure disabled (draft) templates cannot be deleted while their hook exists — verify `canDelete()` checks hook existence, not enabled status

**Checkpoint**: Template protection rules enforced. Connected = protected, Orphaned = deletable.

---

## Phase 8: Polish & Cross-Cutting Concerns

- [x] T017 Update `afterInstall()` to run sync so templates are created immediately on plugin install
- [x] T018 Add `hasHandle(string $handle): bool` convenience method to `src/services/HookService.php`
- [x] T019 Verify `craft dispatch/sync` handles soft-deleted templates correctly (uses `->status(null)` to find all, avoids duplicate handle conflicts)
- [x] T020 Add console output formatting to sync command — use Craft's console helpers for colored output (success/warning/error)

---

## Dependencies & Execution Order

### Phase Dependencies

- **Phase 1 (Setup)**: No dependencies — remove side-effect sync
- **Phase 2 (Foundational)**: Depends on Phase 1 — creates the sync command
- **Phases 3-5 (US1, US2, US3)**: All depend on Phase 2. Can proceed in parallel with each other.
- **Phases 6-7 (US4, US5)**: Depend on Phase 4 (status column). Mostly verification of existing behavior.
- **Phase 8 (Polish)**: After all user stories complete.

### User Story Dependencies

- **US1 (Sync Banner)**: Phase 2 only — independent
- **US2 (Status Column)**: Phase 2 only — independent (already partially implemented)
- **US3 (Failure Logging)**: Phase 2 only — independent
- **US4 (Orphan Visibility)**: Depends on US2 (status column renders orphan state)
- **US5 (Template Protection)**: Phase 2 only — independent (already partially implemented)

### Parallel Opportunities

Within Phase 2: T002 and T004 can run in parallel (different files).
Phases 3, 4, 5: All independent — can run in parallel.
Within Phase 7: T014, T015, T016 touch the same file but are independent changes.

---

## Implementation Strategy

### MVP First (Phases 1-5)

1. Phase 1: Remove auto-sync from init
2. Phase 2: Build `craft dispatch/sync` command
3. Phase 3: Sync status banner in CP
4. Phase 4: Three-state status column
5. Phase 5: Send failure logging
6. **STOP and VALIDATE**: All P1 user stories complete

### Incremental Delivery

1. Phases 1-2 → Sync command works, templates created explicitly
2. Add US1 (banner) → Admin sees sync status immediately
3. Add US2 (status column) → Each template shows its state
4. Add US3 (failure logging) → No silent failures
5. Add US4+US5 (orphan + protection) → Full lifecycle management

---

## Notes

- Most of US2 and US5 are already partially implemented (hookStatus, canDelete). Tasks are verification + enhancement.
- No schema changes needed — existing tables support all requirements.
- No new Twig template files needed for the CP views — Craft's element index handles the table rendering. Only the banner needs a template update or inline rendering in the controller.
- The sync command is the biggest new piece of code.
