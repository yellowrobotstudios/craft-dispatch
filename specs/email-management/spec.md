# Feature Specification: Email Management Plugin

**Feature Name**: email-management
**Created**: 2026-03-09
**Status**: Draft

## Problem Statement

Craft CMS lacks a built-in way to manage transactional and notification email templates from the control panel. Developers currently hard-code email templates in the file system, making it difficult for content editors to modify email copy, and there's no standard pattern for wiring Craft events to email sends. Each project re-invents email handling with bespoke event listeners, making maintenance and handoff painful.

## Target Users

- **Site Administrators / Content Editors**: Need to edit email copy, preview emails, and manage which emails are sent without developer involvement.
- **Developers**: Need to register new email triggers quickly via a config file, pass contextual data to templates, and extend the system with custom hooks.

## User Scenarios & Acceptance Criteria

### Scenario 1: Content Editor Edits an Email Template

**As a** site administrator, **I want to** edit email templates in the Craft control panel **so that** I can update email copy without deploying code changes.

**Acceptance Criteria**:
- User can navigate to a dedicated email section in the CP sidebar
- User can see a list of all registered email templates
- User can edit the subject line and Twig body of any template
- User can use standard Craft variable syntax in templates (e.g., `{{ user.email }}`, `{{ entry.title }}`)
- Changes are saved and take effect immediately on next email send

### Scenario 2: Content Editor Previews an Email

**As a** site administrator, **I want to** preview an email template with real or sample data **so that** I can verify the email looks correct before it gets sent.

**Acceptance Criteria**:
- User can click a "Preview" action from the template edit screen
- User can supply or select sample data objects (e.g., a specific user, entry, or order) to populate template variables
- Preview renders the full email as it would appear to the recipient (HTML and plain text)
- Preview updates live or on-demand as the user modifies template content

### Scenario 3: Developer Registers a New Email Hook

**As a** developer, **I want to** register a new email trigger by adding an entry to a config file **so that** I can wire any Craft event to an email send without writing boilerplate listener code.

**Acceptance Criteria**:
- Developer creates or edits a plugin config file (e.g., `config/craft-dispatch.php`)
- Each hook entry maps a Craft event constant (e.g., `\craft\elements\User::EVENT_AFTER_SAVE`) to an email template handle
- Hook entries support closures for transforming event data into template variables
- Hook entries can define recipient logic (closure or field reference)
- Adding a new hook requires no changes to plugin source code — config only
- New hooks appear automatically in the CP template list

### Scenario 4: Developer Passes Custom Data to a Template

**As a** developer, **I want to** define a closure in the config that transforms event data into template variables **so that** each email template receives exactly the data it needs.

**Acceptance Criteria**:
- Config closures receive the event object and return an associative array of template variables
- Template variables are available in Twig as top-level variables
- If a closure throws an exception, the email is not sent and the error is logged
- Developers can define a `to` closure that returns one or more recipient email addresses

### Scenario 5: System Sends an Email When a Hook Fires

**As the** system, **when** a registered Craft event fires, **it should** automatically send the associated email to the defined recipients.

**Acceptance Criteria**:
- When a Craft event fires that matches a registered hook, the system renders the associated template with the transformed data
- The rendered email is sent via Craft's built-in mailer (respecting existing mail transport settings)
- If the template has been edited in the CP, the CP version is used; otherwise, a default template is used
- Failed sends are logged with the error details
- Emails are queued through Craft's queue system for non-blocking sends

## Functional Requirements

### Email Template Management (Mandatory)

- **FR-1**: The plugin adds a CP section (sidebar nav item) for managing email templates.
- **FR-2**: Each email template has: handle (unique identifier), name (display label), subject line (Twig-enabled), HTML body (Twig), and optional plain-text body (Twig).
- **FR-3**: Templates are stored in the database so they can be edited from the CP without file system access.
- **FR-4**: Templates support all standard Craft Twig functions and variables available in the front-end.
- **FR-5**: The plugin provides a set of default/starter templates for each registered hook that can be overridden via the CP.

### Email Preview (Mandatory)

- **FR-6**: Each template edit screen includes a preview capability.
- **FR-7**: Preview accepts an object reference or raw data (JSON) to populate template variables.
- **FR-8**: Preview renders both HTML and plain-text versions.
- **FR-9**: Preview is available without sending the email.

### Hook Configuration (Mandatory)

- **FR-10**: Hooks are defined in a PHP config file (`config/craft-dispatch.php`) that returns an array.
- **FR-11**: Each hook entry specifies: a Craft event constant, the email template handle, a data transformer closure, and recipient logic.
- **FR-12**: The config file supports closures (anonymous functions) for data transformation and recipient resolution, following the same pattern as the Scout plugin's index configuration.
- **FR-13**: Hooks are registered at plugin initialization and listen for the specified events.
- **FR-14**: New hooks defined in config are automatically available in the CP (template list shows all registered handles, creating default templates as needed).

### Email Sending (Mandatory)

- **FR-15**: When a hooked event fires, the plugin renders the template and sends the email via Craft's mailer.
- **FR-16**: Email sends are processed through Craft's queue to avoid blocking the request.
- **FR-17**: Failed sends are logged with full error context.
- **FR-18**: The plugin respects Craft's existing email transport configuration (SMTP, etc.).

### Email Logging (Optional)

- **FR-19**: The plugin logs sent emails (recipient, template handle, timestamp, status) for auditing purposes.
- **FR-20**: Logs are viewable in the CP.

## Key Entities

- **Email Template**: handle, name, subject, htmlBody, textBody, dateCreated, dateUpdated
- **Email Hook** (config-defined, not stored): eventClass, eventName, templateHandle, dataTransformer (closure), recipientResolver (closure), enabled
- **Email Log**: id, templateHandle, recipient, status, errorMessage, dateSent

## Assumptions

- The plugin targets Craft CMS 4.x or 5.x (modern Craft with PHP 8.x support).
- Closures in config files are standard practice in the Craft ecosystem (Scout, Commerce, etc.) and will work with Craft's config loading.
- Craft's built-in mailer and queue system are sufficient — no need for a custom mail transport layer.
- Email templates will be relatively simple transactional emails, not complex marketing campaigns.
- The plugin does not need to handle email template versioning — the current version is the active version.
- Preview data can be manually entered or selected from existing elements; live data sync is not required.

## Constraints

- Must work within Craft's plugin architecture and follow Craft plugin conventions.
- Must not override or conflict with Craft's built-in system email templates (password reset, etc.) unless explicitly configured.
- Config file must be PHP (not YAML/JSON) to support closures.

## Dependencies

- Craft CMS (4.x or 5.x)
- Craft's built-in mailer component
- Craft's queue system

## Success Criteria

- A non-developer can edit and preview any email template within 2 minutes of navigating to the email section.
- A developer can register a new email hook (event-to-template mapping) by adding fewer than 15 lines to the config file.
- All emails triggered by hooks are sent within 30 seconds of the event firing (via queue).
- The plugin handles 100% of registered events without missed sends under normal operation.
- Template previews accurately reflect the final sent email for a given data set.
