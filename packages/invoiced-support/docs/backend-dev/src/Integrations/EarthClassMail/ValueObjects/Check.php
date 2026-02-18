<?php

namespace App\Integrations\EarthClassMail\ValueObjects;

final class Check
{
    public function __construct(
        public readonly int $amount_in_cents,
        public readonly string $check_number,
        public readonly string $id,
    ) {
    }
}
