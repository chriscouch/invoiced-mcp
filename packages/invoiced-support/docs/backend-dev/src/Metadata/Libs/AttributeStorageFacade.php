<?php

namespace App\Metadata\Libs;

use App\Core\Facade;
use App\Metadata\Storage\AttributeStorage;
use Psr\Log\LoggerInterface;

class AttributeStorageFacade extends Facade
{
    public static ?self $instance = null;

    private AttributeStorage $storage;

    public function __construct(LoggerInterface $logger, AttributeHelper $helper)
    {
        $this->storage = new AttributeStorage($helper);
        $this->storage->setLogger($logger);
    }

    /**
     * Gets an instance of DBAL.
     */
    public static function get(): AttributeStorage
    {
        if (!self::$instance) {
            self::$instance = self::$container->get(self::class); /* @phpstan-ignore-line */
        }

        return self::$instance->storage; /* @phpstan-ignore-line */
    }
}
