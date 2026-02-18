<?php

namespace App\Core;

/**
 * This class is used to provide a global reference to
 * the Symfony service container. It is an anti-pattern
 * and should not be used for new code. The only valid
 * use for this class is to assist migrating off of Infuse
 * framework that also had a global service container instance.
 *
 * @deprecated
 */
class EnvironmentFacade extends Facade
{
    public static ?self $instance;

    public function __construct(private string $appProtocol, private string $appDomain, private int $appPort, private string $appTitle, private string $dashboardUrl)
    {
    }

    /**
     * Gets the protocol of the application.
     */
    public static function getAppProtocol(): string
    {
        if (!self::$instance) {
            self::$instance = self::$container->get(self::class); /* @phpstan-ignore-line */
        }

        return self::$instance->appProtocol; /* @phpstan-ignore-line */
    }

    /**
     * Gets the domain of the application.
     */
    public static function getAppDomain(): string
    {
        if (!self::$instance) {
            self::$instance = self::$container->get(self::class); /* @phpstan-ignore-line */
        }

        return self::$instance->appDomain; /* @phpstan-ignore-line */
    }

    /**
     * Gets the port of the application server.
     */
    public static function getAppPort(): int
    {
        if (!self::$instance) {
            self::$instance = self::$container->get(self::class); /* @phpstan-ignore-line */
        }

        return self::$instance->appPort; /* @phpstan-ignore-line */
    }

    /**
     * Gets the title of the application.
     */
    public static function getAppTitle(): string
    {
        if (!self::$instance) {
            self::$instance = self::$container->get(self::class); /* @phpstan-ignore-line */
        }

        return self::$instance->appTitle; /* @phpstan-ignore-line */
    }

    /**
     * Gets the dashboard URL.
     */
    public static function getDashboardUrl(): string
    {
        if (!self::$instance) {
            self::$instance = self::$container->get(self::class); /* @phpstan-ignore-line */
        }

        return self::$instance->dashboardUrl; /* @phpstan-ignore-line */
    }
}
