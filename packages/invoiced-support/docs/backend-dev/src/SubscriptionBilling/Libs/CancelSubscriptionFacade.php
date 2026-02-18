<?php

namespace App\SubscriptionBilling\Libs;

use App\Core\Facade;
use App\SubscriptionBilling\Operations\CancelSubscription;

/**
 * This class is used to provide a global reference to
 * the Symfony service container. It is an anti-pattern
 * and should not be used for new code. The only valid
 * use for this class is to assist migrating off of Infuse
 * framework that also had a global service container instance.
 *
 * @deprecated
 */
class CancelSubscriptionFacade extends Facade
{
    public static ?self $instance = null;

    public function __construct(private CancelSubscription $cancelSubscription)
    {
    }

    public static function get(): CancelSubscription
    {
        if (!self::$instance) {
            self::$instance = self::$container->get(self::class); /* @phpstan-ignore-line */
        }

        return self::$instance->cancelSubscription; /* @phpstan-ignore-line */
    }
}
