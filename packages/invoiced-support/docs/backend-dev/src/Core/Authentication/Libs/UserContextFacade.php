<?php

namespace App\Core\Authentication\Libs;

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
class UserContextFacade extends Facade
{
    public static ?self $instance = null;

    public function __construct(private UserContext $userContext)
    {
    }

    /**
     * Gets an instance of user context.
     */
    public static function get(): UserContext
    {
        if (!self::$instance) {
            self::$instance = self::$container->get(self::class); /* @phpstan-ignore-line */
        }

        return self::$instance->userContext; /* @phpstan-ignore-line */
    }
}
