<?php

namespace yellowrobot\craftdispatch\models;

use Closure;

class EmailHook
{
    public string $handle;
    public ?string $eventClass = null;
    public ?string $eventName = null;
    public ?Closure $transformer = null;
    public ?Closure $recipientResolver = null;
    public ?Closure $ccResolver = null;
    public ?Closure $bccResolver = null;
    public ?Closure $condition = null;
    public ?string $layoutTemplate = null;
    public string $sendMode = 'list'; // 'list' or 'individual'
    public ?string $previewElementType = null;
    public array $previewCriteria = [];
    public array|string $previewSources = '*';
    public array $channels = [];
    public string|Closure|null $slackTarget = null;
    public string|Closure|null $webhookTarget = null;
    public string|Closure|null $smsTarget = null;

    private function __construct(string $handle)
    {
        $this->handle = $handle;
    }

    public static function create(string $handle): static
    {
        return new static($handle);
    }

    public function event(string $class, string $eventName): static
    {
        $this->eventClass = $class;
        $this->eventName = $eventName;
        return $this;
    }

    public function transformer(callable $fn): static
    {
        $this->transformer = $fn;
        return $this;
    }

    public function to(callable $fn): static
    {
        $this->recipientResolver = $fn;
        return $this;
    }

    public function when(callable $fn): static
    {
        $this->condition = $fn;
        return $this;
    }

    public function cc(callable $fn): static
    {
        $this->ccResolver = $fn;
        return $this;
    }

    public function bcc(callable $fn): static
    {
        $this->bccResolver = $fn;
        return $this;
    }

    public function sendIndividually(): static
    {
        $this->sendMode = 'individual';
        return $this;
    }

    public function layout(string $template): static
    {
        $this->layoutTemplate = $template;
        return $this;
    }

    public function via(string|array $channels): static
    {
        $this->channels = (array) $channels;
        return $this;
    }

    public function slack(string|Closure $target): static
    {
        $this->slackTarget = $target;
        if (!in_array('slack', $this->channels)) {
            $this->channels[] = 'slack';
        }
        return $this;
    }

    public function webhook(string|Closure $target): static
    {
        $this->webhookTarget = $target;
        if (!in_array('webhook', $this->channels)) {
            $this->channels[] = 'webhook';
        }
        return $this;
    }

    public function sms(string|Closure $target): static
    {
        $this->smsTarget = $target;
        if (!in_array('sms', $this->channels)) {
            $this->channels[] = 'sms';
        }
        return $this;
    }

    /**
     * Get effective channels. Defaults to ['email'] if none set.
     */
    public function getChannels(): array
    {
        return !empty($this->channels) ? $this->channels : ['email'];
    }

    public function preview(string $elementType, array $criteria = [], array|string $sources = '*'): static
    {
        $this->previewElementType = $elementType;
        $this->previewCriteria = $criteria;
        $this->previewSources = $sources;
        return $this;
    }

    public function validate(): bool
    {
        if (empty($this->handle) || $this->eventClass === null || $this->eventName === null || $this->transformer === null) {
            return false;
        }

        $plugin = \yellowrobot\craftdispatch\CraftDispatch::$plugin ?? null;

        foreach ($this->getChannels() as $channel) {
            if ($plugin) {
                $engineClass = $plugin->getEngine($channel);
                if ($engineClass && !$engineClass::validateHook($this)) {
                    return false;
                }
            } else {
                if (!$this->_validateChannelFallback($channel)) {
                    return false;
                }
            }
        }

        return true;
    }

    private function _validateChannelFallback(string $channel): bool
    {
        return match ($channel) {
            'email' => $this->recipientResolver !== null,
            'slack' => $this->slackTarget !== null,
            'webhook' => $this->webhookTarget !== null && $this->recipientResolver !== null,
            'sms' => $this->smsTarget !== null,
            default => true,
        };
    }
}
