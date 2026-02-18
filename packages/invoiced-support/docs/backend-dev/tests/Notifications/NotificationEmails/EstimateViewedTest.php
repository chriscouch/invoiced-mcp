<?php

namespace App\Tests\Notifications\NotificationEmails;

use App\Notifications\Enums\NotificationEventType;
use App\Notifications\Models\NotificationEvent;
use App\Notifications\NotificationEmails\EstimateViewed;

class EstimateViewedTest extends AbstractNotificationEmailTest
{
    private array $estimates;

    private function addEvent(): void
    {
        self::hasEstimate();
        $event = new NotificationEvent(['id' => -1]);
        $event->setType(NotificationEventType::EstimateViewed);
        $event->object_id = self::$estimate->id;
        self::$events[] = $event;
        $estimate = self::$estimate->toArray();
        $estimate['customer'] = self::$customer->toArray();
        $this->estimates[] = $estimate;
    }

    private function getEmail(): EstimateViewed
    {
        return new EstimateViewed(self::getService('test.database'));
    }

    public function testProcess(): void
    {
        self::hasCustomer();
        $this->addEvent();

        $email = $this->getEmail();

        $this->assertEquals(
            [
                'subject' => 'Estimate was viewed in customer portal',
            ],
            $email->getMessage(self::$events)
        );
        $this->assertEquals('notifications/estimate-viewed', $email->getTemplate(self::$events));
        $this->assertEquals($this->estimates, $email->getVariables(self::$events)['estimates']);
    }

    public function testProcessBulk(): void
    {
        $email = $this->getEmail();
        self::hasCustomer();
        $this->addEvent();
        $this->addEvent();
        $this->addEvent();
        $this->assertEquals(
            [
                'subject' => 'Estimate was viewed in customer portal',
            ],
            $email->getMessage(self::$events)
        );
        $this->assertEquals('notifications/estimate-viewed-bulk', $email->getTemplate(self::$events));
        $this->assertEquals(
            [
                'count' => 4,
            ],
            $email->getVariables(self::$events)
        );
    }
}
