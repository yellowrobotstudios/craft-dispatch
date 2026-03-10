# Research: Email Management Plugin

## Decision 1: Data Storage — Custom Element Type vs Active Record

**Decision**: Use a custom Element type for email templates, and ActiveRecord for email logs.

**Rationale**: Custom elements give us native Craft CP index pages (sortable, searchable, filterable), status management, soft deletes, and the standard element editor UI — all for free. The Campaign plugin (the gold standard Craft email plugin) uses 5 element types. Email logs are write-heavy audit data that don't need element features, so ActiveRecord is more appropriate there.

**Alternatives considered**:
- Plain ActiveRecord for everything — would require building custom CP index/edit pages from scratch
- Craft's built-in `craft\records\` pattern — too low-level, no CP integration

## Decision 2: Config Pattern — Scout-Style Fluent Builder

**Decision**: Use a fluent builder pattern identical to Scout's `ScoutIndex::create()`, via a `EmailHook::create()` class.

**Rationale**: Scout's pattern is proven in the Craft ecosystem. Developers already know it. The fluent API (`->elementType()->criteria()->transformer()`) is readable, IDE-friendly, and supports closures natively. Config lives at `config/craft-dispatch.php`.

**Example config shape**:
```php
return [
    'hooks' => [
        EmailHook::create('welcome-email')
            ->event(\craft\elements\User::class, \craft\base\Element::EVENT_AFTER_SAVE)
            ->transformer(function (\craft\events\ModelEvent $event) {
                $user = $event->sender;
                return ['user' => $user, 'name' => $user->fullName];
            })
            ->to(function (\craft\events\ModelEvent $event) {
                return $event->sender->email;
            })
            ->when(function (\craft\events\ModelEvent $event) {
                return $event->isNew;
            }),
    ],
];
```

**Alternatives considered**:
- Plain arrays — less readable, no IDE autocompletion, harder to validate
- YAML/JSON config — can't support closures
- Event-based registration via module — defeats the purpose of simple config

## Decision 3: Template Rendering — `View::renderString()`

**Decision**: Use `Craft::$app->getView()->renderString($template, $variables)` for rendering template bodies.

**Rationale**: This is Craft's built-in method for rendering Twig strings programmatically. It supports all Craft Twig functions/filters, respects template mode settings, and is used internally by Craft itself. Both subject lines and body content can be rendered this way.

**Alternatives considered**:
- `template_from_string()` Twig function — only works inside Twig, not from PHP
- Raw Twig environment — would miss Craft's extensions and security features

## Decision 4: Email Sending — Queue Job

**Decision**: Create a `SendEmailJob` extending `craft\queue\BaseJob` that handles template rendering and sending via `Craft::$app->getMailer()`.

**Rationale**: Craft's queue system is the standard for async work. The job serializes the template ID, recipient, and variables (as serializable data — not closures). The closure runs at event time to produce the data; the job receives the resolved data.

**Key insight**: Closures can't be serialized into queue jobs. The event listener must execute the transformer and recipient closures immediately, then pass the resulting data (array of variables + recipient string) to the queue job.

## Decision 5: Preview Mechanism

**Decision**: AJAX-based preview endpoint that accepts template content + JSON data and returns rendered HTML.

**Rationale**: The CP edit screen posts the current template content and variable data to a controller action, which renders via `renderString()` and returns the HTML. This can be displayed in an iframe or preview pane. No need for live WebSocket updates — an on-demand "Refresh Preview" approach is simpler and sufficient.

## Decision 6: Craft Version Target

**Decision**: Target Craft CMS 5.x with PHP 8.2+.

**Rationale**: Craft 5 is the current major version. Craft 4 is in maintenance mode. New plugins should target the latest.

## Existing Plugins Landscape

- **Campaign** (putyourlightson) — Full email marketing suite, 5 element types, overkill for transactional emails
- **MailCraft** — Visual builder, event triggers, but no config-file hook system
- **Sprout Email** — Transactional + marketing, subscriber management

**Gap our plugin fills**: None of these offer a simple, Scout-style config file for wiring Craft events to email templates. Our plugin is laser-focused on transactional/notification emails with a developer-friendly hook system and editor-friendly CP interface.
