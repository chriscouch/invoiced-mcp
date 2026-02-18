<?php

namespace App\Core\Templating;

use App\Core\Facade;
use Twig\Environment;

/**
 * This class is used to provide a global reference to
 * the Symfony service container. It is an anti-pattern
 * and should not be used for new code. The only valid
 * use for this class is to assist migrating off of Infuse
 * framework that also had a global service container instance.
 *
 * @deprecated
 */
class TwigFacade extends Facade
{
    public static ?self $instance = null;

    public function __construct(private Environment $twig)
    {
    }

    /**
     * Gets an instance of Twig.
     */
    public static function get(): Environment
    {
        if (!self::$instance) {
            self::$instance = self::$container->get(self::class); /* @phpstan-ignore-line */
        }

        return self::$instance->twig; /* @phpstan-ignore-line */
    }
}
