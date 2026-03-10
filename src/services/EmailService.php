<?php

namespace yellowrobot\craftdispatch\services;

use Craft;
use craft\base\Component;
use craft\mail\Message;
use yellowrobot\craftdispatch\CraftDispatch;
use yellowrobot\craftdispatch\elements\EmailTemplate;

class EmailService extends Component
{
    public function renderAndSend(string $templateHandle, array $recipients, array $variables, array $cc = [], array $bcc = [], string $sendMode = 'list'): bool
    {
        // Extract element info from variables for logging
        [$elementId, $elementType] = $this->_extractElementInfo($variables);

        $template = EmailTemplate::find()->handle($templateHandle)->one();

        if (!$template) {
            Craft::error("Email template not found: {$templateHandle}", __METHOD__);
            CraftDispatch::$plugin->log->log(
                $templateHandle,
                implode(', ', $recipients),
                '',
                'failed',
                "No template found for hook '{$templateHandle}'. Create one in the control panel under Dispatch → Templates.",
                $elementId,
                $elementType,
            );
            return false;
        }

        if (!$template->enabled) {
            Craft::info("Email template is disabled: {$templateHandle}", __METHOD__);
            CraftDispatch::$plugin->log->log(
                $templateHandle,
                implode(', ', $recipients),
                $template->subject,
                'failed',
                "Template '{$templateHandle}' is disabled. Enable it in the control panel to send.",
                $elementId,
                $elementType,
            );
            return false;
        }

        try {
            $rendered = $this->render($templateHandle, $template, $variables);
        } catch (\Throwable $e) {
            Craft::error("Failed to render email template '{$templateHandle}': {$e->getMessage()}", __METHOD__);
            CraftDispatch::$plugin->log->log($templateHandle, implode(', ', $recipients), $template->subject, 'failed', $e->getMessage(), $elementId, $elementType);
            return false;
        }

        $settings = CraftDispatch::$plugin->getSettings();
        $success = true;

        if ($sendMode === 'list') {
            try {
                $message = $this->_buildMessage($rendered, $settings);
                $message->setTo($recipients);

                if (!empty($cc)) {
                    $message->setCc($cc);
                }
                if (!empty($bcc)) {
                    $message->setBcc($bcc);
                }

                $sent = Craft::$app->getMailer()->send($message);
                if ($sent) {
                    CraftDispatch::$plugin->log->log($templateHandle, implode(', ', $recipients), $rendered['subject'], 'sent', null, $elementId, $elementType);
                } else {
                    Craft::error("Mailer returned false for template '{$templateHandle}'", __METHOD__);
                    CraftDispatch::$plugin->log->log($templateHandle, implode(', ', $recipients), $rendered['subject'], 'failed', 'Mailer returned false', $elementId, $elementType);
                    $success = false;
                }
            } catch (\Throwable $e) {
                Craft::error("Failed to send email using template '{$templateHandle}': {$e->getMessage()}", __METHOD__);
                CraftDispatch::$plugin->log->log($templateHandle, implode(', ', $recipients), $rendered['subject'], 'failed', $e->getMessage(), $elementId, $elementType);
                $success = false;
            }
        } else {
            foreach ($recipients as $recipient) {
                try {
                    $message = $this->_buildMessage($rendered, $settings);
                    $message->setTo($recipient);

                    if (!empty($cc)) {
                        $message->setCc($cc);
                    }
                    if (!empty($bcc)) {
                        $message->setBcc($bcc);
                    }

                    $sent = Craft::$app->getMailer()->send($message);
                    if ($sent) {
                        CraftDispatch::$plugin->log->log($templateHandle, $recipient, $rendered['subject'], 'sent', null, $elementId, $elementType);
                    } else {
                        Craft::error("Mailer returned false for '{$recipient}' using template '{$templateHandle}'", __METHOD__);
                        CraftDispatch::$plugin->log->log($templateHandle, $recipient, $rendered['subject'], 'failed', 'Mailer returned false', $elementId, $elementType);
                        $success = false;
                    }
                } catch (\Throwable $e) {
                    Craft::error("Failed to send email to '{$recipient}' using template '{$templateHandle}': {$e->getMessage()}", __METHOD__);
                    CraftDispatch::$plugin->log->log($templateHandle, $recipient, $rendered['subject'], 'failed', $e->getMessage(), $elementId, $elementType);
                    $success = false;
                }
            }
        }

        return $success;
    }

    /**
     * Extract element ID and type from template variables.
     * Looks for the first Element instance in the variables array.
     */
    private function _extractElementInfo(array $variables): array
    {
        foreach ($variables as $value) {
            if ($value instanceof \craft\base\ElementInterface) {
                return [$value->id, get_class($value)];
            }
        }
        return [null, null];
    }

    private function _buildMessage(array $rendered, $settings): Message
    {
        $message = new Message();
        $message->setSubject($rendered['subject']);
        $message->setHtmlBody($rendered['html']);
        $message->setTextBody($rendered['text']);

        if ($settings->fromEmail) {
            $message->setFrom([$settings->fromEmail => $settings->fromName ?? '']);
        }

        return $message;
    }

    /**
     * Render an email template with variables and optional layout wrapping.
     * Used by both send and preview.
     */
    public function render(string $templateHandle, EmailTemplate $template, array $variables): array
    {
        $view = Craft::$app->getView();

        // Ensure site template mode so {% include "_email/..." %} resolves
        $oldMode = $view->getTemplateMode();
        $view->setTemplateMode($view::TEMPLATE_MODE_SITE);

        try {
            $subject = $view->renderString($template->subject, $variables);
            $html = $view->renderString($template->htmlBody, $variables);
            $text = $template->textBody
                ? $view->renderString($template->textBody, $variables)
                : $this->_htmlToText($html);

            // Wrap in layout if one is configured
            $layout = $this->getLayoutForTemplate($templateHandle);

            if ($layout) {
                $html = $view->renderTemplate($layout, array_merge($variables, [
                    'content' => $html,
                    'subject' => $subject,
                ]));
            }
        } finally {
            $view->setTemplateMode($oldMode);
        }

        return [
            'subject' => $subject,
            'html' => $html,
            'text' => $text,
        ];
    }

    /**
     * Convert HTML to readable plain text, preserving table structure.
     */
    private function _htmlToText(string $html): string
    {
        // Normalize line endings
        $text = str_replace(["\r\n", "\r"], "\n", $html);

        // Convert links before table processing so cells get clean text
        $text = preg_replace_callback('/<a[^>]+href=["\']([^"\']+)["\'][^>]*>(.*?)<\/a>/si', function ($match) {
            $url = $match[1];
            $linkText = trim(strip_tags($match[2]));
            // mailto: links — just show the email address
            if (str_starts_with($url, 'mailto:')) {
                return $linkText;
            }
            // Don't duplicate if link text IS the URL
            return $linkText === $url ? $url : "{$linkText} ({$url})";
        }, $text);

        // Convert table rows: each <tr> becomes a line, cells separated by " - "
        $text = preg_replace_callback('/<tr[^>]*>(.*?)<\/tr>/si', function ($match) {
            $cells = [];
            preg_match_all('/<t[hd][^>]*>(.*?)<\/t[hd]>/si', $match[1], $cellMatches);
            foreach ($cellMatches[1] as $cell) {
                $value = trim(strip_tags($cell));
                if ($value !== '') {
                    $cells[] = $value;
                }
            }
            return implode(' - ', $cells) . "\n";
        }, $text);

        // Strip opening block tags (closing ones get newlines below)
        $text = preg_replace('/<(?:table|div|blockquote|ul|ol)[^>]*>/i', '', $text);

        // Block elements get newlines
        $text = preg_replace('/<br\s*\/?>/i', "\n", $text);
        $text = preg_replace('/<\/p>/i', "\n\n", $text);
        $text = preg_replace('/<\/(?:div|h[1-6]|table|blockquote|li)>/i', "\n", $text);

        // Strip remaining tags
        $text = strip_tags($text);

        // Decode entities
        $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');

        // Collapse whitespace: trim each line, then limit consecutive blank lines
        $text = preg_replace('/^[ \t]+|[ \t]+$/m', '', $text);
        $text = preg_replace('/\n{3,}/', "\n\n", $text);

        return trim($text);
    }

    /**
     * Resolve the layout template for a given handle.
     * Hook-level layout takes priority over global default.
     */
    private function getLayoutForTemplate(string $templateHandle): ?string
    {
        $hook = CraftDispatch::$plugin->hook->getHookByHandle($templateHandle);

        if ($hook && $hook->layoutTemplate) {
            return $hook->layoutTemplate;
        }

        return CraftDispatch::$plugin->getSettings()->defaultLayout;
    }
}
