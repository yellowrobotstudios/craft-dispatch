<?php

namespace yellowrobot\craftdispatch\engines\sms;

interface SmsProviderInterface
{
    public function send(string $to, string $body): void;
}
