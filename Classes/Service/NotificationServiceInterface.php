<?php

namespace CodeQ\LinkChecker\Service;

interface NotificationServiceInterface
{
    public function sendNotification(string $subject, array $variables = []): void;
}
