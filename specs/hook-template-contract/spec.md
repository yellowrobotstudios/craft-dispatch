# Feature Specification: Hook-Template Contract

## Overview

The craft-dispatch plugin connects config-defined email hooks to database-stored email templates through an implicit string-matching relationship (handle). This loose coupling creates lifecycle management problems: orphaned templates, silent failures, lost content on renames, and side-effect-heavy synchronization. The relationship between hooks and templates needs to be explicit, resilient to config changes, and transparent to administrators.

## Problem Statement

Administrators and developers cannot confidently manage email hooks and templates because:

1. **Orphaned templates**: Removing a hook from config leaves its template in the database with no indication it's disconnected, no cleanup, and no way to distinguish it from an active template.
2. **Rename = content loss**: Changing a hook handle creates a brand new empty template while the old template (with carefully authored content) becomes orphaned. There's no migration path.
3. **Implicit relationship**: The template element has no formal knowledge of the hook it serves. The connection exists only at runtime via string matching in `renderAndSend()`.
4. **Silent send failures**: If a template is missing or was soft-deleted, the email silently fails. The administrator has no visibility into this.
5. **Side-effect synchronization**: `ensureTemplatesExist()` runs on every CP page load, creating database records as a side effect of viewing the control panel. This is unpredictable and hard to reason about.

## User Scenarios

### Scenario 1: Developer adds a new hook
- Developer adds a new `EmailHook::create('new-notification')` to config
- System detects the new hook and creates a corresponding template
- Admin sees the new template in the CP, clearly marked as needing content
- Template does not send until the admin authors content and enables it

### Scenario 2: Developer removes a hook
- Developer removes a hook from config
- Admin sees the template is now disconnected — clearly flagged as orphaned
- Admin can choose to delete the orphaned template or leave it for reference
- No emails are sent for a hook that no longer exists (already true, but should be visible)

### Scenario 3: Developer renames a hook handle
- Developer changes handle from `old-name` to `new-name` in config
- System detects there's a new handle with no template, and an old template with no hook
- Admin is informed of both the orphan and the new template
- Admin sees the orphaned template (old handle) and the new empty template (new handle) clearly flagged
- Admin manually copies content from the orphaned template to the new one, then deletes the orphan
- A future enhancement may add a CP action to "adopt" an orphaned template into a new hook handle

### Scenario 4: Admin views template list
- Admin opens Dispatch in the CP
- Each template shows its relationship status: connected to a hook, orphaned, or missing content
- Admin can quickly identify which templates need attention

### Scenario 5: Email fails due to missing template
- A hook fires but no template exists (or it was deleted)
- The failure is logged with a clear error message
- Admin can see in the Dispatch logs that a send was attempted but no template was found

## Functional Requirements

### FR1: Explicit Hook Registration
- On plugin init, the system must build a registry of all valid hooks from config
- The registry must be queryable: "give me all hook handles", "does this handle have a hook?"
- Templates must be able to check their own status against this registry

### FR2: Template Lifecycle Management
- New hooks must result in template creation with `enabled = false` and empty/placeholder content
- Templates for removed hooks must be flagged as orphaned (not auto-deleted)
- Template creation must happen at a predictable time, not as a side effect of page views

### FR3: Sync Status Visibility
- Each template must display its hook connection status in the CP index: **Connected**, **Orphaned**, or **Needs Content**
- The CP should surface a summary when hooks and templates are out of sync (e.g. "2 new hooks need templates, 1 orphaned template")

### FR4: Send Failure Transparency
- When a hook fires and no matching enabled template exists, the system must log an error with the hook handle, event details, and intended recipients
- The log entry must be visible in the Dispatch logs section of the CP
- The system must not silently swallow send failures

### FR5: Template Protection
- Connected templates (with an active hook) cannot be deleted from the CP
- Orphaned templates can be deleted
- All templates can be disabled (which prevents sending without deleting content)

## Success Criteria

- Administrators can identify all out-of-sync hooks/templates within 10 seconds of opening the Dispatch CP section
- No email send failures occur silently — every failure appears in the Dispatch log
- Adding a new hook to config results in a visible, actionable template in the CP on the next page load
- Removing a hook from config results in a clearly flagged orphaned template, not a silent leftover
- Template content is never lost due to hook handle changes without explicit admin action

## Key Entities

- **EmailHook** (config): Defines event binding, conditions, recipients, send mode. Identified by `handle`.
- **EmailTemplate** (DB element): Stores subject, htmlBody, textBody, enabled status. Identified by `handle`.
- **Hook Registry** (runtime): In-memory mapping of handle → hook, built from config on init. Source of truth for "which hooks exist."
- **Dispatch Log** (DB): Records every send attempt with status, recipient, and error details.

## Assumptions

- Hooks are added/removed by developers in code — this is not a CP-managed operation
- The number of hooks is small (10-50) — performance of sync checks is not a concern
- Template content is authored by administrators in the CP after a developer adds the hook
- Database is pulled from production to other environments, so template content travels with the DB
- Hook handle renames are rare and handled manually (admin copies content from orphaned template to new one)

## Scope Boundaries

### In Scope
- Hook-template relationship visibility and lifecycle management
- Sync status in CP
- Send failure logging and visibility
- Template creation and orphan detection

### Out of Scope
- File-based template seeding from Twig files
- Project config integration for template content
- Automated content migration between environments
- Version history for template content
