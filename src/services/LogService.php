<?php

namespace yellowrobot\craftdispatch\services;

use craft\base\Component;
use craft\helpers\Db;
use yellowrobot\craftdispatch\records\EmailLogRecord;

class LogService extends Component
{
    public function log(string $templateHandle, string $recipient, string $subject, string $status, ?string $errorMessage = null, ?int $elementId = null, ?string $elementType = null): void
    {
        $record = new EmailLogRecord();
        $record->templateHandle = $templateHandle;
        $record->recipient = $recipient;
        $record->subject = $subject;
        $record->status = $status;
        $record->errorMessage = $errorMessage;
        $record->elementId = $elementId;
        $record->elementType = $elementType;
        $record->dateSent = Db::prepareDateForDb(new \DateTime());
        if (!$record->save()) {
            \Craft::error('Failed to save email log: ' . json_encode($record->getErrors()), __METHOD__);
        }
    }

    public function getRecentLogs(int $limit = 50, int $offset = 0): array
    {
        return EmailLogRecord::find()
            ->orderBy(['dateSent' => SORT_DESC])
            ->limit($limit)
            ->offset($offset)
            ->all();
    }

    public function getLogById(int $id): ?EmailLogRecord
    {
        return EmailLogRecord::findOne($id);
    }

    public function getTotalCount(): int
    {
        return (int) EmailLogRecord::find()->count();
    }
}
