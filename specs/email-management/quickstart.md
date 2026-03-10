# Quickstart & Test Plan: Email Management Plugin

## Integration Scenarios

### Scenario A: Fresh Install

1. Install plugin via Composer: `composer require yellowrobot/craft-dispatch`
2. Enable plugin in Craft CP → Settings → Plugins
3. Verify: "Dispatch" appears in CP sidebar
4. Verify: `craftdispatch_templates` and `craftdispatch_logs` tables created
5. Verify: Templates index page loads (empty state)

### Scenario B: Define a Hook and Send an Email

1. Create `config/craft-dispatch.php`:

   ```php
   <?php

   use yellowrobot\craftdispatch\models\EmailHook;

   return [
       'hooks' => [
           EmailHook::create('test-notification')
               ->event(\craft\elements\Entry::class, \craft\base\Element::EVENT_AFTER_SAVE)
               ->transformer(function ($event) {
                   return ['entry' => $event->sender, 'title' => $event->sender->title];
               })
               ->to(function ($event) {
                   return 'admin@example.com';
               })
               ->when(function ($event) {
                   return $event->sender->section->handle === 'news';
               }),
       ],
   ];
   ```

2. Navigate to Dispatch → Templates in CP
3. Verify: "test-notification" template appears (auto-created with default content)
4. Edit template: set subject to `New post: {{ title }}`, body to `<p>{{ title }} was published.</p>`
5. Save and create a new Entry in the "news" section
6. Verify: email queued in Craft's queue
7. Run queue (`./craft queue/run`)
8. Verify: email sent, log entry created in Dispatch → Logs

### Scenario C: Preview a Template

1. Navigate to Dispatch → Templates → edit "test-notification"
2. Enter JSON in preview panel: `{"title": "Breaking News", "entry": null}`
3. Click Preview / Refresh
4. Verify: subject renders as "New post: Breaking News"
5. Verify: HTML body renders with "Breaking News"
6. Verify: Twig errors display a user-friendly message

### Scenario D: Guard Condition Prevents Send

1. Using the config from Scenario B (has `->when()` checking section handle)
2. Save an Entry in a section other than "news"
3. Verify: no email is queued or sent
4. Verify: no log entry created

### Scenario E: Error Handling

1. Configure a hook with a transformer that throws an exception
2. Trigger the event
3. Verify: no email is sent
4. Verify: error is logged to Craft's log (not in email logs, since it never reached send)

---

## Test Plan

### Unit Tests

| Test                                | What it validates                                     |
|-------------------------------------|-------------------------------------------------------|
| `EmailHook::create()` fluent API    | Builder correctly sets all properties                 |
| `EmailHook` validation              | Missing event/transformer/to produces validation error|
| Template model validation           | Handle uniqueness, required fields                    |
| Transformer execution               | Closure receives event, returns array                 |
| Recipient resolver                   | Returns string or array of strings                    |
| Guard condition                      | `when()` returning false skips the hook               |
| Twig rendering                       | `renderString()` with variables produces correct HTML |
| Twig rendering error                 | Invalid Twig throws catchable exception               |

### Integration Tests

| Test                                | What it validates                                     |
|-------------------------------------|-------------------------------------------------------|
| Plugin install                      | Tables created, CP section registered                 |
| Plugin uninstall                    | Tables dropped cleanly                                |
| Config loading                      | Hooks parsed from config file, listeners registered   |
| Event → Queue Job                   | Firing an event creates a queue job with correct data |
| Queue Job → Email Send              | Job renders template, calls mailer, creates log entry |
| Template CRUD via CP                | Create, read, update, delete templates through CP     |
| Preview controller action           | POST with template + variables returns rendered HTML  |
| Auto-creation of templates          | Hook handles without CP templates get defaults created|
| Disabled template                   | Disabled element skips sending                        |

### Manual QA Checklist

- [ ] CP sidebar shows "Dispatch" with Templates and Logs subnav
- [ ] Template index page lists all registered templates
- [ ] Template edit page loads with subject, HTML body, text body fields
- [ ] Preview pane renders correctly with sample data
- [ ] Preview shows Twig errors gracefully (no white screen)
- [ ] Saving a template persists changes
- [ ] Triggering a hooked event sends the email
- [ ] Email uses CP-edited content (not default)
- [ ] Logs page shows sent/failed emails with details
- [ ] Failed sends show error message in log detail view
- [ ] Plugin works with Craft's queue (both web-based and CLI runner)
- [ ] Uninstalling plugin removes tables cleanly
