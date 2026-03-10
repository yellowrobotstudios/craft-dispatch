<?php

namespace yellowrobot\craftdispatch\engines;

use Craft;
use craft\helpers\App;
use yellowrobot\craftdispatch\CraftDispatch;
use yellowrobot\craftdispatch\engines\sms\SmsProviderInterface;
use yellowrobot\craftdispatch\engines\sms\SnsProvider;
use yellowrobot\craftdispatch\engines\sms\TwilioProvider;
use yellowrobot\craftdispatch\models\EmailHook;
use yii\base\Event;

class SmsEngine extends AbstractEngine
{
    public static function channelName(): string
    {
        return 'sms';
    }

    public static function displayName(): string
    {
        return 'SMS';
    }

    public static function resolveJobConfig(EmailHook $hook, Event $event, array $serializableVariables): ?array
    {
        $phoneNumber = self::resolveTarget($hook->smsTarget, $hook, $event, 'SMS target');
        if (!$phoneNumber) {
            return null;
        }

        return [
            'templateHandle' => $hook->handle,
            'phoneNumber' => $phoneNumber,
            'variables' => $serializableVariables,
        ];
    }

    public static function validateHook(EmailHook $hook): bool
    {
        return $hook->smsTarget !== null;
    }

    public function send(array $config): void
    {
        $rendered = $this->resolveAndRender($config['templateHandle'], $config['variables'], 'sms');
        if (!$rendered) {
            return;
        }

        $body = "{$rendered['subject']}\n\n{$rendered['text']}";

        try {
            $provider = self::getProvider();
            $provider->send($config['phoneNumber'], $body);
            $this->logSuccess($config['templateHandle'], $config['phoneNumber'], $rendered['subject']);
        } catch (\Throwable $e) {
            Craft::error("Failed to send SMS for '{$config['templateHandle']}': {$e->getMessage()}", __METHOD__);
            $this->logFailure($config['templateHandle'], $config['phoneNumber'], $rendered['subject'], $e->getMessage());
        }
    }

    private static function getProvider(): SmsProviderInterface
    {
        $provider = App::env('SMS_PROVIDER') ?: 'twilio';

        return match ($provider) {
            'twilio' => new TwilioProvider(),
            'sns', 'aws' => new SnsProvider(),
            default => throw new \RuntimeException("Unknown SMS provider: {$provider}. Supported: twilio, sns"),
        };
    }
}
