<?php

namespace CodeQ\LinkChecker\Domain\Notification;

interface NotificationServiceInterface
{
    public function sendNotification(string $subject, array $variables = []): void;
}
