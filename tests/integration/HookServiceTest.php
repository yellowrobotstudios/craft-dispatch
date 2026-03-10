<?php

namespace yellowrobot\craftdispatch\tests\integration;

use PHPUnit\Framework\TestCase;
use yellowrobot\craftdispatch\models\EmailHook;
use yellowrobot\craftdispatch\services\HookService;

/**
 * Tests HookService logic — caching, hook lookup, serialization, channel routing.
 */
class HookServiceTest extends TestCase
{
    // ─── Hook cache sentinel behavior ────────────────────────

    public function testCacheSentinelStartsNull(): void
    {
        $service = new HookService();
        $prop = new \ReflectionProperty(HookService::class, 'hooks');

        // Before any call, hooks should be null (sentinel)
        $this->assertNull($prop->getValue($service));
    }

    // ─── _makeSerializable + rehydrate roundtrip ─────────────

    public function testSerializeRehydrateRoundtripForScalars(): void
    {
        $service = new HookService();
        $serialize = new \ReflectionMethod(HookService::class, '_makeSerializable');

        $job = new RehydrateTestJob();

        $original = [
            'name' => 'Test User',
            'count' => 5,
            'active' => true,
            'tags' => ['alpha', 'beta'],
        ];

        $serialized = $serialize->invoke($service, $original);
        $rehydrated = $job->rehydrate($serialized);

        $this->assertSame($original, $rehydrated);
    }

    public function testSerializeRehydrateRoundtripDropsNonSerializable(): void
    {
        $service = new HookService();
        $serialize = new \ReflectionMethod(HookService::class, '_makeSerializable');

        $original = [
            'name' => 'kept',
            'object' => new \stdClass(),
        ];

        $serialized = $serialize->invoke($service, $original);

        $this->assertArrayHasKey('name', $serialized);
        $this->assertArrayNotHasKey('object', $serialized);
    }

    // ─── Channel routing logic ───────────────────────────────

    public function testEmailHookChannelDefaultsToEmail(): void
    {
        $hook = EmailHook::create('test')
            ->event('SomeClass', 'someEvent')
            ->transformer(fn() => [])
            ->to(fn() => 'user@example.com');

        $this->assertSame(['email'], $hook->getChannels());
        $this->assertTrue($hook->validate());
    }

    public function testSlackOnlyHookDoesNotRequireRecipients(): void
    {
        $hook = EmailHook::create('slack-only')
            ->event('SomeClass', 'someEvent')
            ->transformer(fn() => [])
            ->via('slack')
            ->slack('https://hooks.slack.com/test');

        $this->assertTrue($hook->validate());
        $this->assertSame(['slack'], $hook->getChannels());
        $this->assertNull($hook->recipientResolver);
    }

    public function testWebhookRequiresRecipientResolver(): void
    {
        $hookWithout = EmailHook::create('webhook-no-to')
            ->event('SomeClass', 'someEvent')
            ->transformer(fn() => [])
            ->via('webhook')
            ->webhook('https://example.com/hook');

        $this->assertFalse($hookWithout->validate());

        $hookWith = EmailHook::create('webhook-with-to')
            ->event('SomeClass', 'someEvent')
            ->transformer(fn() => [])
            ->to(fn() => 'user@example.com')
            ->via('webhook')
            ->webhook('https://example.com/hook');

        $this->assertTrue($hookWith->validate());
    }

    public function testMultiChannelHookValidation(): void
    {
        $hook = EmailHook::create('multi')
            ->event('SomeClass', 'someEvent')
            ->transformer(fn() => [])
            ->to(fn() => 'user@example.com')
            ->via(['email', 'slack', 'webhook'])
            ->slack('https://hooks.slack.com/test')
            ->webhook('https://example.com/hook');

        $this->assertTrue($hook->validate());
        $channels = $hook->getChannels();
        $this->assertContains('email', $channels);
        $this->assertContains('slack', $channels);
        $this->assertContains('webhook', $channels);
    }

    public function testSlackMethodAddsSlackChannelOnly(): void
    {
        // ->slack() adds 'slack' to channels but does NOT implicitly add 'email'.
        // The hook starts with empty channels; getChannels() defaults to ['email']
        // only when channels array is empty. After ->slack() it's ['slack'].
        $hook = EmailHook::create('test')
            ->slack('https://hooks.slack.com/test');

        $this->assertSame(['slack'], $hook->getChannels());
    }

    public function testWebhookMethodAddsWebhookChannelOnly(): void
    {
        $hook = EmailHook::create('test')
            ->webhook('https://example.com/hook');

        $this->assertSame(['webhook'], $hook->getChannels());
    }

    public function testViaOverridesImplicitDefault(): void
    {
        // Using ->via() explicitly sets channels, overriding the ['email'] default
        $hook = EmailHook::create('test')->via(['slack', 'webhook']);
        $this->assertSame(['slack', 'webhook'], $hook->getChannels());
    }

    public function testEmptyChannelsDefaultsToEmail(): void
    {
        $hook = EmailHook::create('test');
        $this->assertSame(['email'], $hook->getChannels());
    }

    public function testSlackDoesNotDuplicateChannel(): void
    {
        $hook = EmailHook::create('test')
            ->via(['email', 'slack'])
            ->slack('https://hooks.slack.com/test');

        $this->assertSame(['email', 'slack'], $hook->getChannels());
    }
}
