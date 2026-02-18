<?php

namespace App\Tests\Integrations\AccountingSync;

use App\Integrations\AccountingSync\ValueObjects\ReadQuery;
use App\Tests\AppTestCase;
use Carbon\CarbonImmutable;

class ReadQueryTest extends AppTestCase
{
    public function testSerialization(): void
    {
        $query = new ReadQuery();
        $json = (string) json_encode($query);
        $this->assertEquals($query, ReadQuery::fromArray((array) json_decode($json, true)));

        $query = new ReadQuery(
            CarbonImmutable::now()->setMicroseconds(0),
            new CarbonImmutable('2023-01-01'),
            new CarbonImmutable('2024-01-01'),
            true
        );
        $json = (string) json_encode($query);
        $this->assertEquals($query, ReadQuery::fromArray((array) json_decode($json, true)));
    }
}
