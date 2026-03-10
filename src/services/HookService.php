<?php

namespace yellowrobot\craftdispatch\services;

use Craft;
use craft\base\Component;
use craft\helpers\Queue;
use yellowrobot\craftdispatch\elements\EmailTemplate;
use yellowrobot\craftdispatch\jobs\SendNotificationJob;
use yellowrobot\craftdispatch\CraftDispatch;
use yellowrobot\craftdispatch\models\EmailHook;
use yii\base\Event;

class HookService extends Component
{
    /** @var EmailHook[]|null */
    private ?array $hooks = null;

    public function getHookByHandle(string $handle): ?EmailHook
    {
        foreach ($this->getHooks() as $hook) {
            if ($hook->handle === $handle) {
                return $hook;
            }
        }
        return null;
    }

    public function getHooks(): array
    {
        if ($this->hooks !== null) {
            return $this->hooks;
        }

        $this->hooks = [];

        try {
            $config = Craft::$app->config->getConfigFromFile('craft-dispatch');
        } catch (\Throwable $e) {
            Craft::error("Failed to load craft-dispatch config: {$e->getMessage()}", __METHOD__);
            return [];
        }

        if (!is_array($config) || !isset($config['hooks'])) {
            return [];
        }

        foreach ($config['hooks'] as $hook) {
            if (!$hook instanceof EmailHook) {
                Craft::error('Invalid hook entry in craft-dispatch config: expected EmailHook instance', __METHOD__);
                continue;
            }

            if (!$hook->validate()) {
                Craft::error("Invalid email hook '{$hook->handle}': missing required properties (event, transformer, or to)", __METHOD__);
                continue;
            }

            $this->hooks[] = $hook;
        }

        return $this->hooks;
    }

    public function registerListeners(): void
    {
        foreach ($this->getHooks() as $hook) {
            Event::on(
                $hook->eventClass,
                $hook->eventName,
                function (Event $event) use ($hook) {
                    $this->_handleEvent($hook, $event);
                }
            );
        }
    }

    /**
     * Returns the set of hook handles currently registered in config.
     */
    public function getRegisteredHandles(): array
    {
        return array_map(fn(EmailHook $hook) => $hook->handle, $this->getHooks());
    }

    /**
     * Returns whether a given handle has a registered hook.
     */
    public function hasHandle(string $handle): bool
    {
        return $this->getHookByHandle($handle) !== null;
    }

    /**
     * Returns sync status: which hooks have no template and which templates have no hook.
     */
    public function getSyncStatus(): array
    {
        $registeredHandles = $this->getRegisteredHandles();

        $existingHandles = EmailTemplate::find()
            ->status(null)
            ->select(['craftdispatch_templates.handle'])
            ->column();

        return [
            'unlinked' => array_values(array_diff($registeredHandles, $existingHandles)),
            'orphaned' => array_values(array_diff($existingHandles, $registeredHandles)),
        ];
    }

    /**
     * Returns hook handles available for linking to a new or existing template.
     * Excludes handles that already have a template, unless $currentHandle is specified
     * (so the current template's own handle stays in the list).
     */
    public function getAvailableHookOptions(?string $currentHandle = null): array
    {
        $registeredHandles = $this->getRegisteredHandles();

        $takenHandles = EmailTemplate::find()
            ->status(null)
            ->select(['craftdispatch_templates.handle'])
            ->column();

        $options = [];
        foreach ($registeredHandles as $handle) {
            $taken = in_array($handle, $takenHandles, true) && $handle !== $currentHandle;
            if (!$taken) {
                $options[] = [
                    'label' => ucwords(str_replace('-', ' ', $handle)) . " ({$handle})",
                    'value' => $handle,
                ];
            }
        }

        return $options;
    }

    private function _handleEvent(EmailHook $hook, Event $event): void
    {
        // Skip drafts and revisions automatically for element events
        $sender = $event->sender ?? null;
        if ($sender instanceof \craft\base\ElementInterface) {
            if (method_exists($sender, 'getIsDraft') && $sender->getIsDraft()) {
                return;
            }
            if (method_exists($sender, 'getIsRevision') && $sender->getIsRevision()) {
                return;
            }
        }

        // Skip during project config apply / migrations
        if (\Craft::$app->getProjectConfig()->getIsApplyingExternalChanges()) {
            return;
        }

        // Check guard condition
        if ($hook->condition !== null) {
            try {
                if (!($hook->condition)($event)) {
                    return;
                }
            } catch (\Throwable $e) {
                Craft::error("Email hook '{$hook->handle}' condition threw exception: {$e->getMessage()}", __METHOD__);
                return;
            }
        }

        // Run transformer
        try {
            $variables = ($hook->transformer)($event);
        } catch (\Throwable $e) {
            Craft::error("Email hook '{$hook->handle}' transformer threw exception: {$e->getMessage()}", __METHOD__);
            return;
        }

        if (!is_array($variables)) {
            Craft::error("Email hook '{$hook->handle}' transformer must return an array", __METHOD__);
            return;
        }

        $serializableVariables = $this->_makeSerializable($variables);

        foreach ($hook->getChannels() as $channel) {
            $engineClass = CraftDispatch::$plugin->getEngine($channel);
            if (!$engineClass) {
                Craft::warning("Unknown channel '{$channel}' for hook '{$hook->handle}'", __METHOD__);
                continue;
            }

            $config = $engineClass::resolveJobConfig($hook, $event, $serializableVariables);
            if ($config === null) {
                continue;
            }

            Queue::push(new SendNotificationJob([
                'engineClass' => $engineClass,
                'config' => $config,
            ]));
        }
    }

    private function _makeSerializable(array $variables): array
    {
        $result = [];
        foreach ($variables as $key => $value) {
            if (is_scalar($value) || is_null($value)) {
                $result[$key] = $value;
            } elseif (is_array($value)) {
                $result[$key] = $this->_makeSerializable($value);
            } elseif ($value instanceof \craft\base\ElementInterface) {
                // Store element references as IDs for re-querying in the job
                $result[$key] = [
                    '__elementType' => get_class($value),
                    '__elementId' => $value->id,
                ];
            } else {
                // Skip non-serializable values
                Craft::warning("Skipping non-serializable variable '{$key}' in hook transformer output", __METHOD__);
            }
        }
        return $result;
    }
}
