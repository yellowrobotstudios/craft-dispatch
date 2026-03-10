<?php

namespace yellowrobot\craftdispatch\engines;

use yellowrobot\craftdispatch\CraftDispatch;
use yellowrobot\craftdispatch\models\EmailHook;
use yii\base\Event;

class EmailEngine extends AbstractEngine
{
    public static function channelName(): string
    {
        return 'email';
    }

    public static function displayName(): string
    {
        return 'Email';
    }

    public static function resolveJobConfig(EmailHook $hook, Event $event, array $serializableVariables): ?array
    {
        $recipients = self::resolveRecipients($hook, $event);
        if ($recipients === null) {
            return null;
        }

        $cc = self::resolveAddresses($hook->ccResolver, $hook, $event, 'CC');
        $bcc = self::resolveAddresses($hook->bccResolver, $hook, $event, 'BCC');

        return [
            'templateHandle' => $hook->handle,
            'recipients' => $recipients,
            'cc' => $cc,
            'bcc' => $bcc,
            'sendMode' => $hook->sendMode,
            'variables' => $serializableVariables,
        ];
    }

    public static function validateHook(EmailHook $hook): bool
    {
        return $hook->recipientResolver !== null;
    }

    public function send(array $config): void
    {
        CraftDispatch::$plugin->email->renderAndSend(
            $config['templateHandle'],
            $config['recipients'],
            $config['variables'],
            $config['cc'] ?? [],
            $config['bcc'] ?? [],
            $config['sendMode'] ?? 'list',
        );
    }
}
