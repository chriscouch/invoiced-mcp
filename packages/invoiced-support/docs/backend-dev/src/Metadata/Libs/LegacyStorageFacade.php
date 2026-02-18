<?php

namespace App\Metadata\Libs;

use App\Core\Facade;
use App\Metadata\Storage\LegacyMetadataStorage;
use Doctrine\DBAL\Connection;

class LegacyStorageFacade extends Facade
{
    public static ?self $instance = null;

    private LegacyMetadataStorage $storage;

    public function __construct(Connection $database)
    {
        $this->storage = new LegacyMetadataStorage($database);
    }

    /**
     * Gets an instance of DBAL.
     */
    public static function get(): LegacyMetadataStorage
    {
        if (!self::$instance) {
            self::$instance = self::$container->get(self::class); /* @phpstan-ignore-line */
        }

        return self::$instance->storage; /* @phpstan-ignore-line */
    }
}
