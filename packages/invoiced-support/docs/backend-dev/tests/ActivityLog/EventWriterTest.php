<?php

namespace App\Tests\ActivityLog;

use App\Core\Statsd\StatsdClient;
use App\Core\Utils\ModelNormalizer;
use App\ActivityLog\Enums\EventType;
use App\ActivityLog\Libs\EventWriter;
use App\ActivityLog\Models\Event;
use App\ActivityLog\Storage\NullStorage;
use App\ActivityLog\ValueObjects\PendingDeleteEvent;
use App\ActivityLog\ValueObjects\PendingUpdateEvent;
use App\Tests\AppTestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;

class EventWriterTest extends AppTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasCompany();
        self::hasCustomer();
    }

    private function getWriter(): EventWriter
    {
        return self::getService('test.event_writer');
    }

    public function testWrite(): void
    {
        $associations = [
            ['customer', self::$customer->id()],
            ['estimate', 1],
            ['invoice', 2],
            ['estimate', 0],
        ];
        $events = [
            new PendingUpdateEvent(self::$customer, EventType::CustomerUpdated, ['test' => true], $associations, ['test' => false]),
            new PendingDeleteEvent(self::$customer, EventType::CustomerDeleted, ['test' => true], $associations, [], ['user' => -3]),
        ];

        $writer = $this->getWriter();
        $writer->write($events);

        // look up models to verify results
        /** @var Event[] $events */
        $events = Event::all();

        $storage = self::getService('test.event_storage');
        $this->assertCount(2, $events);
        $expected = [
            'id' => $events[1]->id,
            'type' => EventType::CustomerUpdated->value,
            'timestamp' => $events[1]->timestamp,
            'data' => [
                'object' => (object) array_merge(ModelNormalizer::toArray(self::$customer), ['test' => true]),
                'previous' => (object) ['test' => false],
            ],
        ];
        $events[1]->hydrateFromStorage($storage);
        $this->assertEquals($expected, $events[1]->toArray());
        $expected = [
            'id' => $events[0]->id,
            'type' => EventType::CustomerDeleted->value,
            'timestamp' => $events[0]->timestamp,
            'data' => [
                'object' => (object) ['test' => true],
            ],
        ];
        $events[0]->hydrateFromStorage($storage);
        $this->assertEquals($expected, $events[0]->toArray());

        // check the associations were written
        $expected = [
            'estimate' => 1,
            'invoice' => 2,
            'customer' => self::$customer->id(),
        ];
        $this->assertEquals($expected, $events[0]->getAssociations());
        $this->assertEquals($expected, $events[1]->getAssociations());
    }

    public function testDispatch(): void
    {
        $events = [
            new PendingUpdateEvent(self::$customer, EventType::CustomerUpdated, [], [], ['name' => 'Old Name']),
        ];

        $dispatcher = new EventDispatcher();
        $numDispatches = 0;
        /** @var Event $event */
        $event = null;
        $dispatcher->addListener('object_event.dispatch', function (Event $theEvent) use (&$numDispatches, &$event) {
            $event = $theEvent;
            ++$numDispatches;
        });

        $writer = new EventWriter(self::getService('test.database'), new NullStorage(), $dispatcher, self::getService('test.user_context'));
        $writer->setLogger(self::$logger);
        $writer->setStatsd(new StatsdClient());
        $writer->write($events);
        $this->assertEquals(1, $numDispatches);
        $expected = [
            'id' => $event->id,
            'timestamp' => $event->timestamp,
            'type' => 'customer.updated',
            'data' => [
                'object' => (object) ModelNormalizer::toArray(self::$customer),
                'previous' => (object) [
                    'name' => 'Old Name',
                ],
            ],
        ];
        $this->assertEquals($expected, $event->toArray());
    }
}
