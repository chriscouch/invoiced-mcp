<?php

namespace App\Notifications\Libs;

use App\Core\Facade;

/**
 * @deprecated
 */
class NotificationSpoolFacade extends Facade
{
    public static ?self $instance = null;

    public function __construct(private NotificationSpool $spool)
    {
    }

    /**
     * Gets an instance of the spool.
     */
    public static function get(): NotificationSpool
    {
        if (!self::$instance) {
            /** @var self $object - this is PHPStan complains on */
            $object = self::$container->get(self::class);
            self::$instance = $object;
        }

        return self::$instance->spool;
    }

    public static function set(NotificationSpool $spool): void
    {
        self::get();
        if (self::$instance) {
            self::$instance->spool = $spool;
        }
    }
}
