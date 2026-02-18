<?php

namespace App\Core\Mailer;

use App\Core\Facade;

/**
 * This class is used to provide a global reference to
 * the Symfony service container. It is an anti-pattern
 * and should not be used for new code. The only valid
 * use for this class is to assist migrating off of Infuse
 * framework that also had a global service container instance.
 *
 * @deprecated
 */
class MailerFacade extends Facade
{
    public static ?self $instance = null;

    public function __construct(private Mailer $mailer)
    {
    }

    /**
     * Gets an instance of mailer.
     */
    public static function get(): Mailer
    {
        if (!self::$instance) {
            self::$instance = self::$container->get(self::class); /* @phpstan-ignore-line */
        }

        return self::$instance->mailer; /* @phpstan-ignore-line */
    }
}
