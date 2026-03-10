<?php

namespace yellowrobot\craftdispatch\engines;

use Craft;
use yellowrobot\craftdispatch\CraftDispatch;
use yellowrobot\craftdispatch\models\EmailHook;
use yii\base\Event;

class WebhookEngine extends AbstractEngine
{
    public static function channelName(): string
    {
        return 'webhook';
    }

    public static function displayName(): string
    {
        return 'Webhook';
    }

    public static function resolveJobConfig(EmailHook $hook, Event $event, array $serializableVariables): ?array
    {
        $webhookUrl = self::resolveTarget($hook->webhookTarget, $hook, $event, 'webhook target');
        if (!$webhookUrl) {
            return null;
        }

        $recipients = self::resolveRecipients($hook, $event);
        if ($recipients === null) {
            return null;
        }

        return [
            'templateHandle' => $hook->handle,
            'webhookUrl' => $webhookUrl,
            'recipients' => $recipients,
            'variables' => $serializableVariables,
        ];
    }

    public static function validateHook(EmailHook $hook): bool
    {
        return $hook->webhookTarget !== null && $hook->recipientResolver !== null;
    }

    public function send(array $config): void
    {
        $rendered = $this->resolveAndRender($config['templateHandle'], $config['variables'], 'webhook');
        if (!$rendered) {
            return;
        }

        $payload = [
            'handle' => $config['templateHandle'],
            'subject' => $rendered['subject'],
            'html' => $rendered['html'],
            'text' => $rendered['text'],
            'recipients' => $config['recipients'],
        ];

        try {
            Craft::createGuzzleClient()->post($config['webhookUrl'], [
                'json' => $payload,
                'timeout' => 10,
            ]);
            $this->logSuccess($config['templateHandle'], 'webhook', $rendered['subject']);
        } catch (\Throwable $e) {
            Craft::error("Failed to send webhook for '{$config['templateHandle']}': {$e->getMessage()}", __METHOD__);
            $this->logFailure($config['templateHandle'], 'webhook', $rendered['subject'], $e->getMessage());
        }
    }
}
