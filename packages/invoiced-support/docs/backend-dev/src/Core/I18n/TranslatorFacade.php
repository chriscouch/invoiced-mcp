<?php

namespace App\Core\I18n;

use App\Core\Facade;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * This class is used to provide a global reference to
 * the Symfony service container. It is an anti-pattern
 * and should not be used for new code. The only valid
 * use for this class is to assist migrating off of Infuse
 * framework that also had a global service container instance.
 *
 * @deprecated
 */
class TranslatorFacade extends Facade
{
    public static ?self $instance = null;

    public function __construct(private TranslatorInterface $translator)
    {
    }

    /**
     * Gets an instance of the translator.
     */
    public static function get(): TranslatorInterface
    {
        if (!self::$instance) {
            self::$instance = self::$container->get(self::class); /* @phpstan-ignore-line */
        }

        return self::$instance->translator; /* @phpstan-ignore-line */
    }
}
