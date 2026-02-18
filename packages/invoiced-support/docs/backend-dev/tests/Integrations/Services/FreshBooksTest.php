<?php

namespace App\Tests\Integrations\Services;

use App\Integrations\Enums\IntegrationType;

class FreshBooksTest extends AbstractOAuthIntegrationTest
{
    protected function getIntegrationType(): IntegrationType
    {
        return IntegrationType::FreshBooks;
    }

    public function testGetExtra(): void
    {
        $integration = $this->getIntegration();
        $this->assertEquals((object) ['sync_profile' => null], $integration->getExtra());
    }
}
