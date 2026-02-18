<?php

namespace App\Integrations\EarthClassMail\ValueObjects;

final class Media
{
    public function __construct(
        public readonly string $url,
        public readonly string $content_type,
    ) {
    }
}
