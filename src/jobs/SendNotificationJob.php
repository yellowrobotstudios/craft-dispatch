<?php

namespace yellowrobot\craftdispatch\jobs;

use craft\queue\BaseJob;
use yellowrobot\craftdispatch\engines\AbstractEngine;
use yellowrobot\craftdispatch\traits\RehydratesVariablesTrait;

class SendNotificationJob extends BaseJob
{
    use RehydratesVariablesTrait;

    public string $engineClass;
    public array $config = [];

    public function execute($queue): void
    {
        if (isset($this->config['variables'])) {
            $this->config['variables'] = $this->_rehydrateVariables($this->config['variables']);
        }

        /** @var AbstractEngine $engine */
        $engine = new $this->engineClass();
        $engine->send($this->config);
    }

    protected function defaultDescription(): ?string
    {
        $channel = $this->engineClass::displayName();
        $handle = $this->config['templateHandle'] ?? 'unknown';
        return "Sending {$channel} notification: {$handle}";
    }
}
