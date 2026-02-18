<?php

namespace App\Tests\Notifications\NotificationEmails;

use App\Chasing\Models\Task;
use App\Notifications\Enums\NotificationEventType;
use App\Notifications\Models\NotificationEvent;
use App\Notifications\NotificationEmails\TaskAssigned;

class TaskAssignedTest extends AbstractNotificationEmailTest
{
    private array $tasks;

    private function addEvent(): void
    {
        $task = new Task();
        $task->name = 'Send shut off notice';
        $task->action = 'mail';
        $task->due_date = time();
        $task->customer_id = (int) self::$customer->id();
        $task->saveOrFail();

        $event = new NotificationEvent(['id' => -1]);
        $event->setType(NotificationEventType::TaskAssigned);
        $event->object_id = $task->id;
        self::$events[] = $event;

        $task = $task->toArray();
        $task['customer'] = self::$customer->toArray();
        $this->tasks[] = $task;
    }

    public function testProcess(): void
    {
        self::hasCustomer();
        $this->addEvent();

        $email = new TaskAssigned(self::getService('test.database'));

        $this->assertEquals(
            [
                'subject' => 'Task was assigned to you',
            ],
            $email->getMessage(self::$events)
        );
        $this->assertEquals('notifications/task', $email->getTemplate(self::$events));
        $this->assertEquals($this->tasks, $email->getVariables(self::$events)['tasks']);
    }

    public function testProcessBulk(): void
    {
        $email = new TaskAssigned(self::getService('test.database'));

        $this->addEvent();
        self::hasCustomer();
        $this->addEvent();
        $this->addEvent();
        $this->assertEquals(
            [
                'subject' => 'Task was assigned to you',
            ],
            $email->getMessage(self::$events)
        );
        $this->assertEquals('notifications/task-bulk', $email->getTemplate(self::$events));
        $this->assertEquals(
            [
                'count' => 4,
            ],
            $email->getVariables(self::$events)
        );
    }
}
