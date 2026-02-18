<?php

namespace App\Notifications\Interfaces;

use App\Notifications\Models\NotificationEvent;

interface NotificationEmailInterface
{
    /**
     * Gets the email parameters that are passed to the mailer when sending.
     *
     * @param NotificationEvent[] $events
     */
    public function getMessage(array $events): array;

    /**
     * Gets the name of the email template to send.
     *
     * @param NotificationEvent[] $events
     */
    public function getTemplate(array $events): string;

    /**
     * Gets the variables that go with the email template.
     *
     * @param NotificationEvent[] $events
     */
    public function getVariables(array $events): array;
}
