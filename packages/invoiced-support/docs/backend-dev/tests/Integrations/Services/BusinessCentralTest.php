<?php

namespace App\Tests\Integrations\Services;

use App\Integrations\Enums\IntegrationType;

class BusinessCentralTest extends AbstractOAuthIntegrationTest
{
    protected function getIntegrationType(): IntegrationType
    {
        return IntegrationType::BusinessCentral;
    }

    public function testGetExtra(): void
    {
        $integration = $this->getIntegration();
        $this->assertEquals((object) ['sync_profile' => null], $integration->getExtra());
    }
}
