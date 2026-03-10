# Research: Hook-Template Contract

## Decision 1: When should template sync happen?

**Decision**: Replace the per-CP-request `ensureTemplatesExist()` with a console command and a one-time check on plugin install/update.

**Rationale**: Creating DB records as a side effect of viewing the CP is unpredictable. Craft plugins conventionally do setup work in migrations or via console commands. A `craft dispatch/sync` command is explicit and predictable. Additionally, the `afterInstall()` hook can run sync on first install.

**Alternatives considered**:
- Keep current approach (per-request sync) — rejected because it's a side effect
- Only sync via migration — rejected because new hooks are added between migrations
- Sync on settings save — rejected because settings may never be visited

**Compromise**: Keep the per-CP-request check but make it lightweight — only detect mismatches and surface them as a banner/notice, don't auto-create. The console command does the actual creation.

## Decision 2: How should orphan detection work?

**Decision**: Compare registered hook handles against existing template handles on each CP index load. This is a cheap query (10-50 handles) and the result is used to render the status column.

**Rationale**: The `getRegisteredHandles()` method already exists. Comparing two small arrays is negligible cost. No need for a persistent "orphaned" flag in the DB — it's always computed from current state.

**Alternatives considered**:
- Store an `isOrphaned` column in the DB — rejected because it would get stale and need its own sync
- Only compute on console command — rejected because the CP needs to show status in real time

## Decision 3: How should missing-template send failures be logged?

**Decision**: When `renderAndSend()` finds no template (or a disabled one), log to `craftdispatch_logs` with status `'failed'` and a descriptive error message. Currently it only logs to Craft's error log, not to the dispatch logs table.

**Rationale**: The dispatch logs CP section is where admins look for email issues. Failures should appear there, not buried in `storage/logs/`.

**Alternatives considered**:
- Only log to Craft log — rejected because admins don't check those
- Send a notification to admin — over-engineered for now

## Decision 4: Template creation — enabled or disabled by default?

**Decision**: Create new templates with `enabled = false`. The spec calls for this explicitly.

**Rationale**: A template with placeholder content shouldn't send. The admin must consciously author content and enable it. This prevents accidental sends of "Default email template for..." content.

**Current behavior**: Templates are created with `enabled = true` — this needs to change.
