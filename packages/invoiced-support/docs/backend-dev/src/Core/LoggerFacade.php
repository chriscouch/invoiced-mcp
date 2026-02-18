<?php

namespace App\Core;

use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;

/**
 * This class is used to provide a global reference to
 * the Symfony service container. It is an anti-pattern
 * and should not be used for new code. The only valid
 * use for this class is to assist migrating off of Infuse
 * framework that also had a global service container instance.
 *
 * @deprecated
 */
class LoggerFacade extends Facade implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    public static ?self $instance = null;

    /**
     * Gets an instance of the logger.
     */
    public static function get(): LoggerInterface
    {
        if (!self::$instance) {
            self::$instance = self::$container->get(self::class); /* @phpstan-ignore-line */
        }

        return self::$instance->logger; /* @phpstan-ignore-line */
    }
}
