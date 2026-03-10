<?php

namespace yellowrobot\craftdispatch\jobs;

use Craft;
use craft\queue\BaseJob;
use yellowrobot\craftdispatch\CraftDispatch;
use yellowrobot\craftdispatch\traits\RehydratesVariablesTrait;

class SendSlackJob extends BaseJob
{
    use RehydratesVariablesTrait;

    public string $templateHandle;
    public string $webhookUrl;
    public array $variables = [];

    public function execute($queue): void
    {
        $variables = $this->_rehydrateVariables($this->variables);

        $template = \yellowrobot\craftdispatch\elements\EmailTemplate::find()
            ->handle($this->templateHandle)
            ->one();

        if (!$template) {
            Craft::error("Email template not found for Slack: {$this->templateHandle}", __METHOD__);
            return;
        }

        if (!$template->enabled) {
            Craft::info("Email template is disabled (Slack skipped): {$this->templateHandle}", __METHOD__);
            return;
        }

        try {
            $rendered = CraftDispatch::$plugin->email->render($this->templateHandle, $template, $variables);
        } catch (\Throwable $e) {
            Craft::error("Failed to render template for Slack '{$this->templateHandle}': {$e->getMessage()}", __METHOD__);
            CraftDispatch::$plugin->log->log($this->templateHandle, 'slack', $template->subject, 'failed', $e->getMessage());
            return;
        }

        $payload = [
            'text' => "*{$rendered['subject']}*\n\n{$rendered['text']}",
        ];

        try {
            $client = Craft::createGuzzleClient();
            $client->post($this->webhookUrl, [
                'json' => $payload,
                'timeout' => 10,
            ]);
            CraftDispatch::$plugin->log->log($this->templateHandle, 'slack', $rendered['subject'], 'sent');
        } catch (\Throwable $e) {
            Craft::error("Failed to send Slack message for '{$this->templateHandle}': {$e->getMessage()}", __METHOD__);
            CraftDispatch::$plugin->log->log($this->templateHandle, 'slack', $rendered['subject'], 'failed', $e->getMessage());
        }
    }

    protected function defaultDescription(): ?string
    {
        return "Sending Slack notification: {$this->templateHandle}";
    }
}
