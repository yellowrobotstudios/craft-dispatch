<?php

namespace yellowrobot\craftdispatch\tests\unit;

use PHPUnit\Framework\TestCase;
use yellowrobot\craftdispatch\models\Settings;

class SettingsTest extends TestCase
{
    // ─── Defaults ────────────────────────────────────────────

    public function testDefaultsAreNull(): void
    {
        $settings = new Settings();

        $this->assertNull($settings->fromEmail);
        $this->assertNull($settings->fromName);
        $this->assertNull($settings->defaultLayout);
    }

    // ─── Validation ──────────────────────────────────────────

    public function testValidEmailPasses(): void
    {
        $settings = new Settings();
        $settings->fromEmail = 'test@example.com';

        $this->assertTrue($settings->validate(['fromEmail']));
    }

    public function testInvalidEmailFails(): void
    {
        $settings = new Settings();
        $settings->fromEmail = 'not-an-email';

        $this->assertFalse($settings->validate(['fromEmail']));
    }

    public function testNullEmailPasses(): void
    {
        $settings = new Settings();
        $settings->fromEmail = null;

        $this->assertTrue($settings->validate(['fromEmail']));
    }

    public function testFromNameValidation(): void
    {
        $settings = new Settings();
        $settings->fromName = 'My Site';

        $this->assertTrue($settings->validate(['fromName']));
    }

    public function testFromNameTooLongFails(): void
    {
        $settings = new Settings();
        $settings->fromName = str_repeat('a', 256);

        $this->assertFalse($settings->validate(['fromName']));
    }

    public function testFromNameMaxLengthPasses(): void
    {
        $settings = new Settings();
        $settings->fromName = str_repeat('a', 255);

        $this->assertTrue($settings->validate(['fromName']));
    }
}
