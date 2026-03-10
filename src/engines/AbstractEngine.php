<?php

namespace yellowrobot\craftdispatch\engines;

use Craft;
use yellowrobot\craftdispatch\CraftDispatch;
use yellowrobot\craftdispatch\elements\EmailTemplate;
use yellowrobot\craftdispatch\models\EmailHook;
use yii\base\Event;

abstract class AbstractEngine
{
    abstract public static function channelName(): string;

    abstract public static function displayName(): string;

    /**
     * Resolve serializable job config from hook + event.
     * Returns null to skip this channel.
     */
    abstract public static function resolveJobConfig(EmailHook $hook, Event $event, array $serializableVariables): ?array;

    /**
     * Validate that the hook is properly configured for this channel.
     */
    abstract public static function validateHook(EmailHook $hook): bool;

    /**
     * Execute delivery. Called from inside the queue job with rehydrated variables.
     */
    abstract public function send(array $config): void;

    /**
     * Shared: look up template, check enabled, render. Returns null on failure.
     */
    protected function resolveAndRender(string $templateHandle, array $variables, string $channel): ?array
    {
        $template = EmailTemplate::find()->handle($templateHandle)->one();

        if (!$template) {
            Craft::error("Email template not found for {$channel}: {$templateHandle}", __METHOD__);
            return null;
        }

        if (!$template->enabled) {
            Craft::info("Email template is disabled ({$channel} skipped): {$templateHandle}", __METHOD__);
            return null;
        }

        try {
            return CraftDispatch::$plugin->email->render($templateHandle, $template, $variables);
        } catch (\Throwable $e) {
            Craft::error("Failed to render template for {$channel} '{$templateHandle}': {$e->getMessage()}", __METHOD__);
            CraftDispatch::$plugin->log->log($templateHandle, $channel, $template->subject, 'failed', $e->getMessage());
            return null;
        }
    }

    protected function logSuccess(string $templateHandle, string $recipient, string $subject): void
    {
        CraftDispatch::$plugin->log->log($templateHandle, $recipient, $subject, 'sent');
    }

    protected function logFailure(string $templateHandle, string $recipient, string $subject, string $error): void
    {
        CraftDispatch::$plugin->log->log($templateHandle, $recipient, $subject, 'failed', $error);
    }

    /**
     * Shared helper: resolve a string|Closure target to a string URL.
     */
    protected static function resolveTarget(string|\Closure|null $target, EmailHook $hook, Event $event, string $label): ?string
    {
        if ($target === null) {
            return null;
        }

        if ($target instanceof \Closure) {
            try {
                $target = $target($event);
            } catch (\Throwable $e) {
                Craft::error("Email hook '{$hook->handle}' {$label} resolver threw exception: {$e->getMessage()}", __METHOD__);
                return null;
            }
        }

        return is_string($target) ? $target : null;
    }

    /**
     * Shared helper: resolve recipients from a hook's recipientResolver.
     */
    protected static function resolveRecipients(EmailHook $hook, Event $event): ?array
    {
        if ($hook->recipientResolver === null) {
            return null;
        }

        try {
            $recipients = ($hook->recipientResolver)($event);
        } catch (\Throwable $e) {
            Craft::error("Email hook '{$hook->handle}' recipient resolver threw exception: {$e->getMessage()}", __METHOD__);
            return null;
        }

        if (is_string($recipients)) {
            $recipients = array_map('trim', explode(',', $recipients));
        }

        if (!is_array($recipients)) {
            Craft::error("Email hook '{$hook->handle}' recipient resolver must return a string or array", __METHOD__);
            return null;
        }

        $recipients = array_values(array_filter($recipients));

        return !empty($recipients) ? $recipients : null;
    }

    /**
     * Shared helper: resolve CC or BCC from a closure.
     */
    protected static function resolveAddresses(?callable $resolver, EmailHook $hook, Event $event, string $label): array
    {
        if ($resolver === null) {
            return [];
        }

        try {
            $result = $resolver($event);
            if (is_string($result)) {
                $result = array_map('trim', explode(',', $result));
            }
            return is_array($result) ? array_values(array_filter($result)) : [];
        } catch (\Throwable $e) {
            Craft::error("Email hook '{$hook->handle}' {$label} resolver threw exception: {$e->getMessage()}", __METHOD__);
            return [];
        }
    }
}
