<?php

namespace App\Automations\Libs;

use App\Automations\Interfaces\NormalizerInterface;
use InvalidArgumentException;
use Symfony\Component\DependencyInjection\ServiceLocator;

readonly class NormalizerFactory
{
    public function __construct(private ServiceLocator $handlerLocator)
    {
    }

    /**
     * Gets an exporter instance of a given type.
     *
     * @throws InvalidArgumentException
     */
    public function get(string $type, mixed $value): mixed
    {
        if (!$this->handlerLocator->has($type)) {
            throw new InvalidArgumentException('Normalizer class does not exist for type: '.$type);
        }

        /** @var NormalizerInterface $normalizer */
        $normalizer = $this->handlerLocator->get($type);

        return $normalizer->normalize($value);
    }
}
