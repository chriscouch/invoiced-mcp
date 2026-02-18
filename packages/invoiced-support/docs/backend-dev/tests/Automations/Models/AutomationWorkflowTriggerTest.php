<?php

namespace App\Tests\Automations\Models;

use App\Automations\Enums\AutomationTriggerType;
use App\Automations\Models\AutomationWorkflowTrigger;
use App\Tests\AppTestCase;
use Carbon\CarbonImmutable;
use App\Core\Orm\Event\ModelCreating;

class AutomationWorkflowTriggerTest extends AppTestCase
{
    /**
     * @dataProvider rRuleProvider
     */
    public function testSaveRRule(string $rule, CarbonImmutable $expected): void
    {
        $trigger = new AutomationWorkflowTrigger();
        $trigger->trigger_type = AutomationTriggerType::Schedule;
        $trigger->r_rule = $rule;
        $event = new ModelCreating($trigger);

        AutomationWorkflowTrigger::saveRRule($event);

        $this->assertEquals($expected, CarbonImmutable::parse($trigger->next_run));
    }

    public function rRuleProvider(): array
    {
        $hour = CarbonImmutable::now()->hour;

        return [
            'hourly' => [
                'RRULE:FREQ=HOURLY;INTERVAL=1',
                CarbonImmutable::now()->addHour()->startOfHour(),
            ],
            'hourly_by_hour1' => [
                'RRULE:FREQ=HOURLY;INTERVAL=1;BYHOUR=14',
                CarbonImmutable::parse('next 2PM'),
            ],
            'hourly_by_hour2' => [
                'RRULE:FREQ=HOURLY;INTERVAL=1;BYHOUR=2',
                CarbonImmutable::parse('next 2AM'),
            ],
            'daily' => [
                'RRULE:FREQ=DAILY;INTERVAL=1',
                CarbonImmutable::now()->addDay()->startOfHour(),
            ],
            'daily_by_hour1' => [
                'RRULE:FREQ=DAILY;INTERVAL=1;BYHOUR=14',
                CarbonImmutable::parse('next 2PM'),
            ],
            'daily_by_hour2' => [
                'RRULE:FREQ=DAILY;INTERVAL=1;BYHOUR=2',
                CarbonImmutable::parse('next 2AM'),
            ],
            'weekly' => [
                'RRULE:FREQ=WEEKLY;INTERVAL=1;BYDAY=MO',
                CarbonImmutable::parse('Monday '.$hour.':00')->startOfHour()->lessThan(CarbonImmutable::now())
                    ? CarbonImmutable::parse('Monday '.$hour.':00')->addWeek()->startOfHour()
                    : CarbonImmutable::parse('Monday '.$hour.':00')->startOfHour(),
            ],
            'weekly_by_hour' => [
                'RRULE:FREQ=WEEKLY;INTERVAL=1;BYDAY=TU;BYHOUR=23',
                CarbonImmutable::parse('tuesday 11PM')->startOfHour()->lessThan(CarbonImmutable::now())
                    ? CarbonImmutable::parse('tuesday 11PM')->addWeek()->startOfHour()
                    : CarbonImmutable::parse('tuesday 11PM')->startOfHour(),
            ],
            'monthly' => [
                'RRULE:FREQ=MONTHLY;INTERVAL=1;BYMONTHDAY=4',
                CarbonImmutable::now()->day(4)->startOfHour()->lessThan(CarbonImmutable::now())
                    ? CarbonImmutable::now()->day(4)->addMonth()->startOfHour()
                    : CarbonImmutable::now()->day(4)->startOfHour(),
            ],
            'monthly_by_hour' => [
                'RRULE:FREQ=MONTHLY;INTERVAL=1;BYMONTHDAY=1;BYHOUR=14',
                CarbonImmutable::parse('first day of next month')->setHour(14)->startOfHour(),
            ],
            'yearly1' => [
                'RRULE:FREQ=YEARLY;INTERVAL=1;BYMONTH=6;BYMONTHDAY=4',
                CarbonImmutable::now()->day(4)->month(6)->startOfHour()->lessThan(CarbonImmutable::now())
                    ? CarbonImmutable::now()->day(4)->month(6)->addYear()->startOfHour()
                    : CarbonImmutable::now()->day(4)->month(6)->startOfHour(),
            ],
            'yearly2' => [
                'RRULE:FREQ=YEARLY;INTERVAL=1;BYMONTH=6;BYMONTHDAY=4;BYHOUR=14',
                CarbonImmutable::now()->day(4)->month(6)->hour(14)->startOfHour()->lessThan(CarbonImmutable::now())
                    ? CarbonImmutable::now()->day(4)->month(6)->hour(14)->addYear()->startOfHour()
                    : CarbonImmutable::now()->day(4)->month(6)->hour(14)->startOfHour(),
            ],
        ];
    }
}
