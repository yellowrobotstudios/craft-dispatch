<?php

namespace yellowrobot\craftdispatch\engines;

use Craft;
use yellowrobot\craftdispatch\CraftDispatch;
use yellowrobot\craftdispatch\models\EmailHook;
use yii\base\Event;

class SlackEngine extends AbstractEngine
{
    public static function channelName(): string
    {
        return 'slack';
    }

    public static function displayName(): string
    {
        return 'Slack';
    }

    public static function resolveJobConfig(EmailHook $hook, Event $event, array $serializableVariables): ?array
    {
        $webhookUrl = self::resolveSlackUrl($hook, $event);
        if (!$webhookUrl) {
            return null;
        }

        return [
            'templateHandle' => $hook->handle,
            'webhookUrl' => $webhookUrl,
            'variables' => $serializableVariables,
        ];
    }

    public static function validateHook(EmailHook $hook): bool
    {
        return $hook->slackTarget !== null;
    }

    public function send(array $config): void
    {
        $rendered = $this->resolveAndRender($config['templateHandle'], $config['variables'], 'slack');
        if (!$rendered) {
            return;
        }

        $payload = [
            'text' => "*{$rendered['subject']}*\n\n{$rendered['text']}",
        ];

        try {
            Craft::createGuzzleClient()->post($config['webhookUrl'], [
                'json' => $payload,
                'timeout' => 10,
            ]);
            $this->logSuccess($config['templateHandle'], 'slack', $rendered['subject']);
        } catch (\Throwable $e) {
            Craft::error("Failed to send Slack message for '{$config['templateHandle']}': {$e->getMessage()}", __METHOD__);
            $this->logFailure($config['templateHandle'], 'slack', $rendered['subject'], $e->getMessage());
        }
    }

    private static function resolveSlackUrl(EmailHook $hook, Event $event): ?string
    {
        $target = self::resolveTarget($hook->slackTarget, $hook, $event, 'Slack target');
        if ($target === null) {
            return null;
        }

        if (str_starts_with($target, 'https://')) {
            return $target;
        }

        $globalWebhook = CraftDispatch::$plugin->getSettings()->slackWebhookUrl ?? null;
        if (!$globalWebhook) {
            Craft::error("Slack channel '{$target}' specified for hook '{$hook->handle}' but no global slackWebhookUrl configured", __METHOD__);
            return null;
        }

        return $globalWebhook;
    }
}
