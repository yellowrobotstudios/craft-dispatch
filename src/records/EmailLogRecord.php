<?php

namespace yellowrobot\craftdispatch\records;

use craft\db\ActiveRecord;

/**
 * @property int $id
 * @property string $templateHandle
 * @property string $recipient
 * @property string $subject
 * @property string $status
 * @property string|null $errorMessage
 * @property int|null $elementId
 * @property string|null $elementType
 * @property string $dateSent
 */
class EmailLogRecord extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%craftdispatch_logs}}';
    }
}
