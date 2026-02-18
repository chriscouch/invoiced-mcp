<?php

namespace App\Tests\Integrations\Services;

use App\Integrations\Enums\IntegrationType;

class WaveTest extends AbstractOAuthIntegrationTest
{
    protected function getIntegrationType(): IntegrationType
    {
        return IntegrationType::Wave;
    }
}
