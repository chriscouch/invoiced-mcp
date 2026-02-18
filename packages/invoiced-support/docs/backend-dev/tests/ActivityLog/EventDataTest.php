<?php

namespace App\Tests\ActivityLog;

use App\ActivityLog\ValueObjects\EventData;
use App\Tests\AppTestCase;

class EventDataTest extends AppTestCase
{
    public function testJson(): void
    {
        $data = new EventData((object) ['test' => true]);
        $this->assertEquals((object) ['test' => true], $data->object);
        $this->assertNull($data->previous);
        $this->assertEquals($data, EventData::fromJson((string) json_encode($data)));

        $data2 = new EventData((object) ['test' => true], (object) ['previous' => true]);
        $this->assertEquals((object) ['test' => true], $data2->object);
        $this->assertEquals((object) ['previous' => true], $data2->previous);
        $this->assertEquals($data2, EventData::fromJson((string) json_encode($data2)));
    }
}
