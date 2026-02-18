<?php

namespace App\Sending\Email\ValueObjects;

use Stringable;

class NamedAddress implements Stringable
{
    public function __construct(
        private string $address,
        private ?string $name = null,
    ) {
    }

    public function getAddress(): string
    {
        return $this->address;
    }

    public function getName(): ?string
    {
        return $this->name ?: null;
    }

    /**
     * Generates an RFC822 style address, i.e. Jared King <jared@invoiced.com>.
     */
    public function __toString(): string
    {
        if (!$this->name) {
            return $this->address;
        }

        return $this->name.' <'.$this->address.'>';
    }
}
