<?php

namespace yellowrobot\craftdispatch\tests\unit;

use PHPUnit\Framework\TestCase;
use yellowrobot\craftdispatch\models\EmailHook;

class EmailHookTest extends TestCase
{
    // ─── Static factory ──────────────────────────────────────

    public function testCreateReturnsInstance(): void
    {
        $hook = EmailHook::create('welcome-email');
        $this->assertInstanceOf(EmailHook::class, $hook);
        $this->assertSame('welcome-email', $hook->handle);
    }

    // ─── Fluent API returns self ─────────────────────────────

    public function testFluentApiReturnsSelf(): void
    {
        $hook = EmailHook::create('test');

        $this->assertSame($hook, $hook->event('SomeClass', 'someEvent'));
        $this->assertSame($hook, $hook->transformer(fn() => []));
        $this->assertSame($hook, $hook->to(fn() => 'test@example.com'));
        $this->assertSame($hook, $hook->cc(fn() => 'cc@example.com'));
        $this->assertSame($hook, $hook->bcc(fn() => 'bcc@example.com'));
        $this->assertSame($hook, $hook->when(fn() => true));
        $this->assertSame($hook, $hook->layout('_email/layout'));
        $this->assertSame($hook, $hook->sendIndividually());
    }

    // ─── Property assignment ─────────────────────────────────

    public function testEventSetsProperties(): void
    {
        $hook = EmailHook::create('test')->event('App\\Entry', 'EVENT_AFTER_SAVE');

        $this->assertSame('App\\Entry', $hook->eventClass);
        $this->assertSame('EVENT_AFTER_SAVE', $hook->eventName);
    }

    public function testTransformerSetsClosure(): void
    {
        $fn = fn() => ['key' => 'value'];
        $hook = EmailHook::create('test')->transformer($fn);

        $this->assertSame($fn, $hook->transformer);
    }

    public function testToSetsClosure(): void
    {
        $fn = fn() => 'test@example.com';
        $hook = EmailHook::create('test')->to($fn);

        $this->assertSame($fn, $hook->recipientResolver);
    }

    public function testCcSetsClosure(): void
    {
        $fn = fn() => 'cc@example.com';
        $hook = EmailHook::create('test')->cc($fn);

        $this->assertSame($fn, $hook->ccResolver);
    }

    public function testBccSetsClosure(): void
    {
        $fn = fn() => 'bcc@example.com';
        $hook = EmailHook::create('test')->bcc($fn);

        $this->assertSame($fn, $hook->bccResolver);
    }

    public function testWhenSetsClosure(): void
    {
        $fn = fn() => true;
        $hook = EmailHook::create('test')->when($fn);

        $this->assertSame($fn, $hook->condition);
    }

    public function testLayoutSetsTemplate(): void
    {
        $hook = EmailHook::create('test')->layout('_email/layout');

        $this->assertSame('_email/layout', $hook->layoutTemplate);
    }

    // ─── Send mode ───────────────────────────────────────────

    public function testDefaultSendModeIsList(): void
    {
        $hook = EmailHook::create('test');
        $this->assertSame('list', $hook->sendMode);
    }

    public function testSendIndividuallySetsMode(): void
    {
        $hook = EmailHook::create('test')->sendIndividually();
        $this->assertSame('individual', $hook->sendMode);
    }

    // ─── Defaults for optional properties ────────────────────

    public function testOptionalPropertiesDefaultToNull(): void
    {
        $hook = EmailHook::create('test');

        $this->assertNull($hook->eventClass);
        $this->assertNull($hook->eventName);
        $this->assertNull($hook->transformer);
        $this->assertNull($hook->recipientResolver);
        $this->assertNull($hook->ccResolver);
        $this->assertNull($hook->bccResolver);
        $this->assertNull($hook->condition);
        $this->assertNull($hook->layoutTemplate);
    }

    // ─── Validation ──────────────────────────────────────────

    public function testValidatePassesWithRequiredFields(): void
    {
        $hook = EmailHook::create('test')
            ->event('SomeClass', 'someEvent')
            ->transformer(fn() => [])
            ->to(fn() => 'test@example.com');

        $this->assertTrue($hook->validate());
    }

    public function testValidateFailsWithoutEvent(): void
    {
        $hook = EmailHook::create('test')
            ->transformer(fn() => [])
            ->to(fn() => 'test@example.com');

        $this->assertFalse($hook->validate());
    }

    public function testValidateFailsWithoutTransformer(): void
    {
        $hook = EmailHook::create('test')
            ->event('SomeClass', 'someEvent')
            ->to(fn() => 'test@example.com');

        $this->assertFalse($hook->validate());
    }

    public function testValidateFailsWithoutRecipient(): void
    {
        $hook = EmailHook::create('test')
            ->event('SomeClass', 'someEvent')
            ->transformer(fn() => []);

        $this->assertFalse($hook->validate());
    }

    public function testValidateFailsWithEmptyHandle(): void
    {
        $hook = EmailHook::create('')
            ->event('SomeClass', 'someEvent')
            ->transformer(fn() => [])
            ->to(fn() => 'test@example.com');

        $this->assertFalse($hook->validate());
    }

    public function testValidatePassesWithOptionalFieldsSet(): void
    {
        $hook = EmailHook::create('full-hook')
            ->event('SomeClass', 'someEvent')
            ->transformer(fn() => [])
            ->to(fn() => 'test@example.com')
            ->cc(fn() => 'cc@example.com')
            ->bcc(fn() => 'bcc@example.com')
            ->when(fn() => true)
            ->layout('_email/layout')
            ->sendIndividually();

        $this->assertTrue($hook->validate());
    }

    // ─── Channels ────────────────────────────────────────────

    public function testDefaultChannelIsEmail(): void
    {
        $hook = EmailHook::create('test');
        $this->assertSame(['email'], $hook->getChannels());
    }

    public function testViaSetChannels(): void
    {
        $hook = EmailHook::create('test')->via(['email', 'slack']);
        $this->assertSame(['email', 'slack'], $hook->getChannels());
    }

    public function testViaAcceptsString(): void
    {
        $hook = EmailHook::create('test')->via('slack');
        $this->assertSame(['slack'], $hook->getChannels());
    }

    public function testSlackImplicitlyAddsChannel(): void
    {
        $hook = EmailHook::create('test')->slack('https://hooks.slack.com/test');
        $this->assertContains('slack', $hook->getChannels());
        $this->assertSame('https://hooks.slack.com/test', $hook->slackTarget);
    }

    public function testSlackDoesNotDuplicate(): void
    {
        $hook = EmailHook::create('test')
            ->via(['email', 'slack'])
            ->slack('https://hooks.slack.com/test');
        $this->assertSame(['email', 'slack'], $hook->getChannels());
    }

    public function testWebhookImplicitlyAddsChannel(): void
    {
        $hook = EmailHook::create('test')->webhook('https://example.com/hook');
        $this->assertContains('webhook', $hook->getChannels());
        $this->assertSame('https://example.com/hook', $hook->webhookTarget);
    }

    public function testSlackAcceptsClosure(): void
    {
        $fn = fn() => 'https://hooks.slack.com/test';
        $hook = EmailHook::create('test')->slack($fn);
        $this->assertSame($fn, $hook->slackTarget);
    }

    public function testWebhookAcceptsClosure(): void
    {
        $fn = fn() => 'https://example.com/hook';
        $hook = EmailHook::create('test')->webhook($fn);
        $this->assertSame($fn, $hook->webhookTarget);
    }

    public function testFluentViaReturnsSelf(): void
    {
        $hook = EmailHook::create('test');
        $this->assertSame($hook, $hook->via('email'));
        $this->assertSame($hook, $hook->slack('https://hooks.slack.com/test'));
        $this->assertSame($hook, $hook->webhook('https://example.com/hook'));
    }

    // ─── Validation with channels ────────────────────────────

    public function testValidateSlackOnlyWithoutRecipient(): void
    {
        $hook = EmailHook::create('test')
            ->event('SomeClass', 'someEvent')
            ->transformer(fn() => [])
            ->via('slack')
            ->slack('https://hooks.slack.com/test');

        $this->assertTrue($hook->validate());
    }

    public function testValidateSlackFailsWithoutTarget(): void
    {
        $hook = EmailHook::create('test')
            ->event('SomeClass', 'someEvent')
            ->transformer(fn() => [])
            ->via('slack');

        $this->assertFalse($hook->validate());
    }

    public function testValidateWebhookOnlyWithoutRecipientFails(): void
    {
        $hook = EmailHook::create('test')
            ->event('SomeClass', 'someEvent')
            ->transformer(fn() => [])
            ->via('webhook')
            ->webhook('https://example.com/hook');

        $this->assertFalse($hook->validate());
    }

    public function testValidateWebhookOnlyWithRecipientPasses(): void
    {
        $hook = EmailHook::create('test')
            ->event('SomeClass', 'someEvent')
            ->transformer(fn() => [])
            ->to(fn() => 'test@example.com')
            ->via('webhook')
            ->webhook('https://example.com/hook');

        $this->assertTrue($hook->validate());
    }

    public function testValidateMultiChannelsExplicit(): void
    {
        $hook = EmailHook::create('test')
            ->event('SomeClass', 'someEvent')
            ->transformer(fn() => [])
            ->to(fn() => 'test@example.com')
            ->via(['email', 'slack'])
            ->slack('https://hooks.slack.com/test');

        $this->assertTrue($hook->validate());
        $this->assertContains('email', $hook->getChannels());
        $this->assertContains('slack', $hook->getChannels());
    }

    public function testSlackOnlyDoesNotIncludeEmail(): void
    {
        $hook = EmailHook::create('test')
            ->event('SomeClass', 'someEvent')
            ->transformer(fn() => [])
            ->slack('https://hooks.slack.com/test');

        $this->assertTrue($hook->validate());
        $this->assertSame(['slack'], $hook->getChannels());
    }

    // ─── Full builder chain ──────────────────────────────────

    public function testFullBuilderChain(): void
    {
        $hook = EmailHook::create('order-confirmation')
            ->event('craft\\elements\\Entry', 'EVENT_AFTER_SAVE')
            ->transformer(fn($event) => ['entry' => $event->sender])
            ->to(fn($event) => $event->sender->email ?? 'fallback@example.com')
            ->cc(fn() => 'sales@example.com')
            ->bcc(fn() => 'archive@example.com')
            ->when(fn($event) => !empty($event->sender))
            ->layout('_email/order-layout')
            ->sendIndividually();

        $this->assertSame('order-confirmation', $hook->handle);
        $this->assertSame('craft\\elements\\Entry', $hook->eventClass);
        $this->assertSame('EVENT_AFTER_SAVE', $hook->eventName);
        $this->assertNotNull($hook->transformer);
        $this->assertNotNull($hook->recipientResolver);
        $this->assertNotNull($hook->ccResolver);
        $this->assertNotNull($hook->bccResolver);
        $this->assertNotNull($hook->condition);
        $this->assertSame('_email/order-layout', $hook->layoutTemplate);
        $this->assertSame('individual', $hook->sendMode);
        $this->assertTrue($hook->validate());
    }
}
