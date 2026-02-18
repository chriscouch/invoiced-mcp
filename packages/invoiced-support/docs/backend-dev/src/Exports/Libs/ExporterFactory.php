<?php

namespace App\Exports\Libs;

use App\Exports\Interfaces\ExporterInterface;
use InvalidArgumentException;
use Symfony\Component\DependencyInjection\ServiceLocator;

class ExporterFactory
{
    public function __construct(private readonly ServiceLocator $handlerLocator)
    {
    }

    /**
     * Gets an exporter instance of a given type.
     *
     * @throws InvalidArgumentException
     */
    public function get(string $type): ExporterInterface
    {
        if (!$this->handlerLocator->has($type)) {
            throw new InvalidArgumentException('Exporter class does not exist for type: '.$type);
        }

        return $this->handlerLocator->get($type);
    }
}
