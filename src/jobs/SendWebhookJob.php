<?php

namespace yellowrobot\craftdispatch\jobs;

use Craft;
use craft\queue\BaseJob;
use yellowrobot\craftdispatch\CraftDispatch;
use yellowrobot\craftdispatch\traits\RehydratesVariablesTrait;

class SendWebhookJob extends BaseJob
{
    use RehydratesVariablesTrait;

    public string $templateHandle;
    public string $webhookUrl;
    public array $recipients = [];
    public array $variables = [];

    public function execute($queue): void
    {
        $variables = $this->_rehydrateVariables($this->variables);

        $template = \yellowrobot\craftdispatch\elements\EmailTemplate::find()
            ->handle($this->templateHandle)
            ->one();

        if (!$template) {
            Craft::error("Email template not found for webhook: {$this->templateHandle}", __METHOD__);
            return;
        }

        if (!$template->enabled) {
            Craft::info("Email template is disabled (webhook skipped): {$this->templateHandle}", __METHOD__);
            return;
        }

        try {
            $rendered = CraftDispatch::$plugin->email->render($this->templateHandle, $template, $variables);
        } catch (\Throwable $e) {
            Craft::error("Failed to render template for webhook '{$this->templateHandle}': {$e->getMessage()}", __METHOD__);
            CraftDispatch::$plugin->log->log($this->templateHandle, 'webhook', $template->subject, 'failed', $e->getMessage());
            return;
        }

        $payload = [
            'handle' => $this->templateHandle,
            'subject' => $rendered['subject'],
            'html' => $rendered['html'],
            'text' => $rendered['text'],
            'recipients' => $this->recipients,
        ];

        try {
            $client = Craft::createGuzzleClient();
            $client->post($this->webhookUrl, [
                'json' => $payload,
                'timeout' => 10,
            ]);
            CraftDispatch::$plugin->log->log($this->templateHandle, 'webhook', $rendered['subject'], 'sent');
        } catch (\Throwable $e) {
            Craft::error("Failed to send webhook for '{$this->templateHandle}': {$e->getMessage()}", __METHOD__);
            CraftDispatch::$plugin->log->log($this->templateHandle, 'webhook', $rendered['subject'], 'failed', $e->getMessage());
        }
    }

    protected function defaultDescription(): ?string
    {
        return "Sending webhook: {$this->templateHandle}";
    }
}
