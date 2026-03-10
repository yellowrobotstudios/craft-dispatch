<?php

namespace yellowrobot\craftdispatch\controllers;

use Craft;
use craft\web\Controller;
use yellowrobot\craftdispatch\CraftDispatch;

class LogsController extends Controller
{
    public function beforeAction($action): bool
    {
        $this->requireCpRequest();
        return parent::beforeAction($action);
    }

    public function actionIndex(): \yii\web\Response
    {
        $this->requirePermission('craft-dispatch:manage');

        $page = max(1, (int) Craft::$app->getRequest()->getQueryParam('page', 1));
        $limit = 50;
        $offset = ($page - 1) * $limit;

        $logs = CraftDispatch::$plugin->log->getRecentLogs($limit, $offset);
        $total = CraftDispatch::$plugin->log->getTotalCount();

        return $this->renderTemplate('craft-dispatch/logs/_index', [
            'logs' => $logs,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
        ]);
    }

    public function actionDetail(int $id): \yii\web\Response
    {
        $this->requirePermission('craft-dispatch:manage');

        $log = CraftDispatch::$plugin->log->getLogById($id);

        if (!$log) {
            throw new \yii\web\NotFoundHttpException('Log entry not found');
        }

        // Check if resend is possible (has element + hook)
        $canResend = false;
        if ($log->elementId && $log->elementType) {
            $hook = CraftDispatch::$plugin->hook->getHookByHandle($log->templateHandle);
            $canResend = $hook !== null;
        }

        return $this->renderTemplate('craft-dispatch/logs/_detail', [
            'log' => $log,
            'canResend' => $canResend,
        ]);
    }

    public function actionResend(): \yii\web\Response
    {
        $this->requirePermission('craft-dispatch:manage');
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        $request = Craft::$app->getRequest();
        $logId = $request->getRequiredBodyParam('logId');

        $log = CraftDispatch::$plugin->log->getLogById((int) $logId);
        if (!$log) {
            return $this->asJson(['success' => false, 'error' => 'Log entry not found.']);
        }

        if (!$log->elementId || !$log->elementType) {
            return $this->asJson(['success' => false, 'error' => 'No element associated with this log entry.']);
        }

        $hook = CraftDispatch::$plugin->hook->getHookByHandle($log->templateHandle);
        if (!$hook) {
            return $this->asJson(['success' => false, 'error' => 'No hook found for this template handle.']);
        }

        // Re-fetch the original element
        $element = Craft::$app->getElements()->getElementById((int) $log->elementId, $log->elementType);
        if (!$element) {
            return $this->asJson(['success' => false, 'error' => 'Original element no longer exists.']);
        }

        // Run through the hook's transformer
        $variables = [];
        if ($hook->transformer) {
            try {
                $fakeEvent = new class extends \yii\base\Event {
                    public $subscription;
                };
                $fakeEvent->sender = $element;
                $fakeEvent->subscription = $element;
                $variables = ($hook->transformer)($fakeEvent);
            } catch (\Throwable $e) {
                return $this->asJson(['success' => false, 'error' => "Transformer failed: {$e->getMessage()}"]);
            }
        }

        // Parse recipients from the log
        $recipients = array_map('trim', explode(',', $log->recipient));
        $recipients = array_values(array_filter($recipients));

        if (empty($recipients)) {
            return $this->asJson(['success' => false, 'error' => 'No recipients found in log entry.']);
        }

        $success = CraftDispatch::$plugin->email->renderAndSend(
            $log->templateHandle,
            $recipients,
            $variables,
            [],
            [],
            $hook->sendMode,
        );

        return $this->asJson([
            'success' => $success,
            'message' => $success ? 'Email resent to ' . implode(', ', $recipients) : 'Failed to resend email. Check the logs for details.',
        ]);
    }
}
