<?php

namespace yellowrobot\craftdispatch\controllers;

use Craft;
use craft\web\Controller;
use yellowrobot\craftdispatch\CraftDispatch;
use yellowrobot\craftdispatch\elements\EmailTemplate;

class TemplatesController extends Controller
{
    public function beforeAction($action): bool
    {
        $this->requireCpRequest();
        return parent::beforeAction($action);
    }

    public function actionIndex(): \yii\web\Response
    {
        $this->requirePermission('craft-dispatch:manage');

        $syncStatus = CraftDispatch::$plugin->hook->getSyncStatus();

        return $this->renderTemplate('craft-dispatch/templates/_index', [
            'elementType' => EmailTemplate::class,
            'syncStatus' => $syncStatus,
        ]);
    }

    public function actionCreate(): \yii\web\Response
    {
        $this->requirePermission('craft-dispatch:manage');

        $template = new EmailTemplate();
        $template->enabled = true;

        return Craft::$app->runAction('elements/edit', [
            'element' => $template,
        ]);
    }

    public function actionPreview(): \yii\web\Response
    {
        $this->requirePermission('craft-dispatch:manage');
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        $request = Craft::$app->getRequest();
        $handle = $request->getBodyParam('handle', '');
        $subject = $request->getRequiredBodyParam('subject');
        $htmlBody = $request->getRequiredBodyParam('htmlBody');
        $textBody = $request->getBodyParam('textBody', '');
        $previewElementId = $request->getBodyParam('previewElementId');
        $previewElementType = $request->getBodyParam('previewElementType');

        $variables = [];

        if ($previewElementId && $previewElementType) {
            $hook = $handle ? CraftDispatch::$plugin->hook->getHookByHandle($handle) : null;
            if (!$hook) {
                return $this->asJson([
                    'success' => false,
                    'error' => 'No hook found for this template handle.',
                ]);
            }
            $allowedType = $hook->previewElementType ?? $hook->eventClass;
            if ($allowedType && $previewElementType !== $allowedType) {
                return $this->asJson([
                    'success' => false,
                    'error' => 'Invalid preview element type.',
                ]);
            }

            $previewElement = Craft::$app->getElements()->getElementById(
                (int) $previewElementId,
                $previewElementType,
            );

            if ($previewElement) {
                if ($hook->transformer) {
                    try {
                        $fakeEvent = new class extends \yii\base\Event {
                            public $subscription;
                        };
                        $fakeEvent->sender = $previewElement;
                        $fakeEvent->subscription = $previewElement;
                        $variables = ($hook->transformer)($fakeEvent);
                    } catch (\Throwable $e) {
                        $variables = $this->_elementToVariables($previewElement);
                    }
                } else {
                    $variables = $this->_elementToVariables($previewElement);
                }
            }
        }

        $template = new EmailTemplate();
        $template->subject = $subject;
        $template->htmlBody = $htmlBody;
        $template->textBody = $textBody ?: null;

        try {
            $rendered = CraftDispatch::$plugin->email->render($handle, $template, $variables);
        } catch (\Throwable $e) {
            Craft::error("Preview render failed: {$e->getMessage()}", __METHOD__);
            return $this->asJson([
                'success' => false,
                'error' => 'Failed to render template preview.',
            ]);
        }

        return $this->asJson([
            'success' => true,
            'subject' => $rendered['subject'],
            'html' => $rendered['html'],
            'text' => $rendered['text'],
        ]);
    }

    private function _elementToVariables(\craft\base\Element $element): array
    {
        $className = (new \ReflectionClass($element))->getShortName();
        $varName = lcfirst($className);

        $variables = [
            $varName => $element,
        ];

        if (isset($element->title)) {
            $variables['title'] = $element->title;
        }
        if (method_exists($element, 'getFieldValues')) {
            foreach ($element->getFieldValues() as $handle => $value) {
                $variables[$handle] = $value;
            }
        }
        if ($element instanceof \craft\elements\User) {
            $variables['email'] = $element->email;
            $variables['name'] = $element->fullName;
            $variables['firstName'] = $element->firstName;
            $variables['lastName'] = $element->lastName;
        }

        return $variables;
    }
}
