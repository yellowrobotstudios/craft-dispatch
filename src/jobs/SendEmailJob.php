<?php

namespace yellowrobot\craftdispatch\jobs;

use craft\queue\BaseJob;
use yellowrobot\craftdispatch\CraftDispatch;
use yellowrobot\craftdispatch\traits\RehydratesVariablesTrait;

class SendEmailJob extends BaseJob
{
    use RehydratesVariablesTrait;

    public string $templateHandle;
    public array $recipients = [];
    public array $cc = [];
    public array $bcc = [];
    public string $sendMode = 'list';
    public array $variables = [];

    public function execute($queue): void
    {
        $variables = $this->_rehydrateVariables($this->variables);

        CraftDispatch::$plugin->email->renderAndSend(
            $this->templateHandle,
            $this->recipients,
            $variables,
            $this->cc,
            $this->bcc,
            $this->sendMode,
        );
    }

    protected function defaultDescription(): ?string
    {
        return "Sending email: {$this->templateHandle}";
    }
}
