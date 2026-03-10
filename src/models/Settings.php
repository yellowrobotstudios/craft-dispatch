<?php

namespace yellowrobot\craftdispatch\models;

use craft\base\Model;

class Settings extends Model
{
    public ?string $fromEmail = null;
    public ?string $fromName = null;
    public ?string $defaultLayout = null;
    public ?string $slackWebhookUrl = null;

    public function defineRules(): array
    {
        return [
            [['fromEmail'], 'email'],
            [['fromName'], 'string', 'max' => 255],
            [['slackWebhookUrl'], 'url'],
        ];
    }
}
