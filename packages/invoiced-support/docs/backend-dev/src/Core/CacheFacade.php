<?php

namespace App\Core;

use Symfony\Contracts\Cache\CacheInterface;

/**
 * This class is used to provide a global reference to
 * the Symfony service container. It is an anti-pattern
 * and should not be used for new code. The only valid
 * use for this class is to assist migrating off of Infuse
 * framework that also had a global service container instance.
 *
 * @deprecated
 */
class CacheFacade extends Facade
{
    public static ?self $instance = null;

    public function __construct(private CacheInterface $cache)
    {
    }

    /**
     * Gets an instance of cache.
     */
    public static function get(): CacheInterface
    {
        if (!self::$instance) {
            self::$instance = self::$container->get(self::class); /* @phpstan-ignore-line */
        }

        return self::$instance->cache; /* @phpstan-ignore-line */
    }
}
