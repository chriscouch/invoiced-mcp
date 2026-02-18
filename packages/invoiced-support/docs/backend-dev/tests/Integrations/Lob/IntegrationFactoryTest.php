<?php

namespace App\Tests\Integrations\Lob;

use App\Integrations\Enums\IntegrationType;
use App\Integrations\Interfaces\IntegrationInterface;
use App\Integrations\Libs\IntegrationFactory;
use App\Integrations\Services\Slack;
use App\Tests\AppTestCase;

class IntegrationFactoryTest extends AppTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasCompany();
    }

    private function getIntegrations(): IntegrationFactory
    {
        return new IntegrationFactory();
    }

    public function testGet(): void
    {
        $this->assertInstanceOf(Slack::class, $this->getIntegrations()->get(IntegrationType::Slack, self::$company));
    }

    public function testAll(): void
    {
        $integrations = $this->getIntegrations()->all(self::$company);
        $this->assertGreaterThan(0, count($integrations));
        foreach ($integrations as $integration) {
            $this->assertInstanceOf(IntegrationInterface::class, $integration);
        }
    }
}
