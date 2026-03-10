# Contract: Plugin Configuration File

## File Location

`config/craft-dispatch.php`

## Config Shape

```php
<?php

use yellowrobot\craftdispatch\models\EmailHook;

return [
    // Optional: global "from" override (defaults to Craft's system email)
    'fromEmail' => 'noreply@example.com',
    'fromName' => 'My App',

    // Hook definitions
    'hooks' => [

        // Minimal hook — event + template handle + transformer + recipient
        EmailHook::create('welcome-email')
            ->event(\craft\elements\User::class, \craft\base\Element::EVENT_AFTER_SAVE)
            ->transformer(function (\craft\events\ModelEvent $event) {
                $user = $event->sender;
                return [
                    'user' => $user,
                    'name' => $user->fullName,
                    'email' => $user->email,
                ];
            })
            ->to(function (\craft\events\ModelEvent $event) {
                return $event->sender->email;
            })
            ->when(function (\craft\events\ModelEvent $event) {
                return $event->isNew; // Only on first save (creation)
            }),

        // Hook with multiple recipients
        EmailHook::create('order-confirmation')
            ->event(\craft\elements\Entry::class, \craft\base\Element::EVENT_AFTER_SAVE)
            ->transformer(function (\craft\events\ModelEvent $event) {
                $order = $event->sender;
                return [
                    'order' => $order,
                    'total' => $order->getFieldValue('orderTotal'),
                ];
            })
            ->to(function (\craft\events\ModelEvent $event) {
                return [
                    $event->sender->getFieldValue('customerEmail'),
                    'sales@example.com',
                ];
            })
            ->when(function (\craft\events\ModelEvent $event) {
                return $event->sender->section->handle === 'orders';
            }),
    ],
];
```

## EmailHook Fluent API

### `EmailHook::create(string $handle): static`

Factory method. `$handle` must match an email template handle in the CP.

### `->event(string $class, string $eventName): static`

**Required.** The Craft event to listen to.

- `$class`: Fully qualified class name (e.g., `\craft\elements\User::class`)
- `$eventName`: Event constant (e.g., `\craft\base\Element::EVENT_AFTER_SAVE`)

### `->transformer(callable $fn): static`

**Required.** Transforms the event into template variables.

- Receives: the event object (e.g., `ModelEvent`, `Event`)
- Must return: `array<string, mixed>` — keys become Twig variables
- If throws: email is skipped, error is logged

### `->to(callable $fn): static`

**Required.** Resolves recipient email address(es).

- Receives: the event object
- Must return: `string` (single email) or `string[]` (multiple)

### `->when(callable $fn): static`

**Optional.** Guard condition — hook only fires when this returns `true`.

- Receives: the event object
- Must return: `bool`
- Default: always fires (no condition)

## Validation

On plugin init, each hook is validated:

- `handle` is a non-empty string
- `event()` has been called with valid class + event name
- `transformer()` has been called with a callable
- `to()` has been called with a callable
- Invalid hooks log an error and are skipped (don't crash the app)
