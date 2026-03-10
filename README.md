# Dispatch for Craft CMS

Event-driven transactional email for Craft CMS 5. Define hooks in config, edit templates in the control panel, preview with real data, and send via email, Slack, or webhooks.

## Requirements

- Craft CMS 5.0+
- PHP 8.2+

## Installation

```bash
composer require yellowrobot/craft-dispatch
php craft plugin/install craft-dispatch
```

## How It Works

1. **Define hooks** in `config/craft-dispatch.php` — each hook listens for a Craft event
2. **Edit templates** in the CP — subject, HTML body, and optional plain text, all Twig
3. **When events fire** — the plugin runs your transformer, resolves recipients, and queues the email

Templates are automatically created for each hook on first load.

## Configuration

Create `config/craft-dispatch.php`:

```php
<?php

use yellowrobot\craftdispatch\models\EmailHook;
use craft\elements\Entry;
use craft\elements\User;

return [
    // Global settings
    'fromEmail' => 'noreply@example.com',
    'fromName' => 'My Site',
    'defaultLayout' => '_email/layout',       // site template path
    'slackWebhookUrl' => getenv('SLACK_WEBHOOK_URL'), // global Slack webhook

    // Hooks
    'hooks' => [
        // ... see below
    ],
];
```

### Settings

| Setting | Type | Default | Description |
|---------|------|---------|-------------|
| `fromEmail` | `string\|null` | `null` | Sender email (falls back to Craft's system email) |
| `fromName` | `string\|null` | `null` | Sender name |
| `defaultLayout` | `string\|null` | `null` | Site template path to wrap all emails |
| `slackWebhookUrl` | `string\|null` | `null` | Default Slack incoming webhook URL |

## Hooks

Each hook connects a Craft event to an email template. Hooks use a fluent builder API:

```php
EmailHook::create('welcome-email')                    // handle (matches CP template)
    ->event(User::class, User::EVENT_AFTER_SAVE)       // event class + event name
    ->transformer(fn($event) => [                      // variables for Twig
        'user' => $event->sender,
    ])
    ->to(fn($event) => $event->sender->email)          // recipient(s)
    ->when(fn($event) => $event->sender->firstSave)    // optional guard condition
    ->cc(fn($event) => 'admin@example.com')            // optional CC
    ->bcc(fn($event) => 'archive@example.com')         // optional BCC
    ->sendIndividually()                                // one email per recipient
    ->layout('_email/custom-layout')                    // per-hook layout override
    ->preview(User::class)                              // element type for CP preview
```

### Hook Methods

#### Required

| Method | Description |
|--------|-------------|
| `create(string $handle)` | Static factory. Handle must match the template handle in the CP. |
| `event(string $class, string $eventName)` | The Craft/Yii event to listen for. |
| `transformer(callable $fn)` | Receives the event, returns an array of Twig variables. |
| `to(callable $fn)` | Receives the event, returns a comma-separated string or array of email addresses. Required when using the `email` channel. |

#### Optional

| Method | Description |
|--------|-------------|
| `when(callable $fn)` | Guard condition — return `false` to skip sending. |
| `cc(callable $fn)` | CC recipients. Same signature as `to()`. |
| `bcc(callable $fn)` | BCC recipients. Same signature as `to()`. |
| `sendIndividually()` | Send a separate email to each recipient (default: single email with all recipients in To). |
| `layout(string $template)` | Site template path for this hook's layout. Overrides `defaultLayout`. |
| `preview(string $elementType, array $criteria = [])` | Element type and optional query criteria for the CP preview picker. |
| `via(string\|array $channels)` | Set delivery channels. See [Channels](#channels). |
| `slack(string\|Closure $target)` | Add Slack channel with webhook URL or channel name. |
| `webhook(string\|Closure $target)` | Add webhook channel with URL. |

### Transformer

The transformer receives the raw Yii event and must return an associative array. These become Twig variables in your template:

```php
->transformer(fn($event) => [
    'entry' => $event->sender,          // the Entry that was saved
    'author' => $event->sender->getAuthor(),
])
```

In your CP template:

```twig
<p>{{ entry.title }} was updated by {{ author.fullName }}.</p>
```

**Elements are serializable.** When the plugin queues a send job, elements in your transformer output are automatically stored as type/ID references and rehydrated when the job runs. Scalar values are passed through directly.

### Guard Conditions

The `when()` closure controls whether the hook fires:

```php
->when(function ($event) {
    $entry = $event->sender;
    return $entry->section->handle === 'news'
        && $entry->enabled;
})
```

**Automatic guards:** The plugin automatically skips drafts, revisions, and project config apply operations. You don't need to check for these.

### Recipients

The `to()`, `cc()`, and `bcc()` closures can return:

- A single email string: `'user@example.com'`
- A comma-separated string: `'one@example.com,two@example.com'`
- An array: `['one@example.com', 'two@example.com']`

### Send Modes

**List mode (default):** One email with all recipients in the To field.

**Individual mode:** A separate email to each recipient. Enable with `->sendIndividually()`. CC and BCC are added to each individual email.

## Channels

By default, hooks send via email. You can add Slack and webhook channels:

```php
// Email + Slack
EmailHook::create('order-alert')
    ->event(Entry::class, Entry::EVENT_AFTER_SAVE)
    ->transformer(fn($e) => ['entry' => $e->sender])
    ->to(fn($e) => 'team@example.com')
    ->slack('https://hooks.slack.com/services/T.../B.../xxx')

// Slack only (no email)
EmailHook::create('deploy-notify')
    ->event(Entry::class, Entry::EVENT_AFTER_SAVE)
    ->transformer(fn($e) => ['entry' => $e->sender])
    ->via('slack')
    ->slack('#deployments')    // uses global slackWebhookUrl

// Webhook (posts JSON payload)
EmailHook::create('sync-to-crm')
    ->event(Entry::class, Entry::EVENT_AFTER_SAVE)
    ->transformer(fn($e) => ['entry' => $e->sender])
    ->webhook('https://hooks.zapier.com/...')

// All three
EmailHook::create('critical-alert')
    ->event(Entry::class, Entry::EVENT_AFTER_SAVE)
    ->transformer(fn($e) => ['entry' => $e->sender])
    ->to(fn($e) => 'admin@example.com')
    ->via(['email', 'slack', 'webhook'])
    ->slack('https://hooks.slack.com/services/...')
    ->webhook('https://example.com/hook')
```

### Channel Behavior

| Channel | What it sends | Target |
|---------|--------------|--------|
| `email` | Rendered HTML/text email via Craft mailer | `to()` recipients |
| `slack` | Bold subject + plain text body | Slack incoming webhook URL |
| `webhook` | JSON payload with `handle`, `subject`, `html`, `text`, `recipients` | Any URL |

### Slack Target Resolution

- Full URL (`https://hooks.slack.com/...`) — used directly
- Channel name (`#alerts`) — uses the global `slackWebhookUrl` from settings
- Closure — `->slack(fn($event) => $event->sender->slackWebhook)` for dynamic URLs

## Templates

### Editing

Templates are managed in the CP under **Dispatch > Templates**. Each template has:

- **Title** — display name
- **Handle** — must match the hook handle in your config
- **Subject** — Twig string (e.g., `Welcome, {{ user.fullName }}!`)
- **HTML Body** — full Twig template for the email
- **Plain Text Body** — optional; auto-generated from HTML if blank

All fields support Twig with the variables from your hook's transformer.

### Auto-Creation

When the plugin loads, it checks your config hooks and creates a default template for any hook that doesn't have one yet. You then customize the template in the CP.

### Deletion Protection

Templates tied to a config hook cannot be deleted from the CP.

### Layouts

Layouts wrap the rendered HTML body. A layout template receives:

- `{{ content|raw }}` — the rendered HTML body
- `{{ subject }}` — the rendered subject line
- All transformer variables

Example layout (`templates/_email/layout.twig`):

```twig
<!DOCTYPE html>
<html>
<head>
    <title>{{ subject }}</title>
</head>
<body>
    <div class="header">
        <img src="{{ siteUrl }}images/logo.png" alt="Logo">
    </div>
    <div class="content">
        {{ content|raw }}
    </div>
    <div class="footer">
        <p>&copy; {{ now|date('Y') }} My Company</p>
    </div>
</body>
</html>
```

Set globally via `defaultLayout` in config, or per-hook with `->layout()`.

## Preview

If a hook has `->preview()` configured, the template edit screen shows a live preview panel. Select an element, click **Render**, and see the fully rendered email with real data.

```php
// Show only entries from the "news" section in the picker
->preview(Entry::class, ['section' => 'news'])

// Show all orders
->preview(\craft\commerce\elements\Order::class)

// Filter to specific order types
->preview(\craft\commerce\elements\Order::class, ['shopOrderType' => 'investitureRegistration'])
```

The preview runs the hook's actual transformer to ensure variable names match what the template expects.

## Logs

All sent emails are logged and viewable in the CP under **Dispatch > Logs**. Each log entry records:

- Template handle
- Recipient
- Subject
- Status (`sent` or `failed`)
- Error message (if failed)
- Timestamp

Slack and webhook sends are also logged with `slack` or `webhook` as the recipient.

## Events & Queuing

All sends are queued via Craft's queue system. When an event fires:

1. Automatic guards check for drafts, revisions, and project config applies
2. The `when()` condition is evaluated (if set)
3. The transformer runs and produces variables
4. Recipients are resolved from `to()`, `cc()`, `bcc()`
5. Elements in variables are converted to serializable type/ID references
6. A job is pushed to the queue for each configured channel
7. The job rehydrates elements, renders the template, and sends

This means closures (`to`, `when`, `transformer`) run at event time with full context, while the actual rendering and sending happen asynchronously in the queue.

## Complete Example

```php
<?php
// config/craft-dispatch.php

use yellowrobot\craftdispatch\models\EmailHook;
use craft\elements\Entry;

return [
    'defaultLayout' => '_email/layout',

    'hooks' => [
        EmailHook::create('new-comment-notification')
            ->event(Entry::class, Entry::EVENT_AFTER_SAVE)
            ->transformer(fn($event) => [
                'comment' => $event->sender,
                'author' => $event->sender->getAuthor(),
            ])
            ->to(fn($event) => 'moderators@example.com')
            ->cc(fn($event) => $event->sender->getAuthor()->email)
            ->when(function ($event) {
                $entry = $event->sender;
                return $entry->section->handle === 'comments'
                    && $entry->firstSave;
            })
            ->sendIndividually()
            ->slack('https://hooks.slack.com/services/T.../B.../xxx')
            ->preview(Entry::class, ['section' => 'comments']),
    ],
];
```

Then in the CP, edit the "New Comment Notification" template:

**Subject:** `New comment from {{ author.fullName }}`

**HTML Body:**
```twig
<h2>New Comment</h2>
<p><strong>{{ author.fullName }}</strong> posted a comment:</p>
<blockquote>{{ comment.body }}</blockquote>
<p><a href="{{ comment.cpEditUrl }}">View in CP</a></p>
```
