<?php

namespace App\Sending\Email\Libs;

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
class EmailSpoolFacade extends Facade
{
    public static ?self $instance = null;

    public function __construct(private EmailSpool $spool)
    {
    }

    /**
     * Gets an instance of email sender.
     */
    public static function get(): EmailSpool
    {
        if (!self::$instance) {
            self::$instance = self::$container->get(self::class); /* @phpstan-ignore-line */
        }

        return self::$instance->spool; /* @phpstan-ignore-line */
    }
}
