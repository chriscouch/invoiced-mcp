<?php

namespace App\Tests\Core\Billing;

use App\Companies\Models\Company;
use App\Core\Mailer\Mailer;
use App\Core\Statsd\StatsdClient;
use App\EntryPoint\CronJob\SendTrialReminders;
use App\Tests\AppTestCase;
use Mockery;
use App\Core\Orm\Iterator;

class SendTrialRemindersTest extends AppTestCase
{
    public function testGetTrialsEndingSoon(): void
    {
        $job = $this->getJob();

        $start = strtotime('+2 days');
        $end = strtotime('+3 days');

        /** @var Iterator $members */
        $members = $job->getTrialsEndingSoon();

        $this->assertInstanceOf(Iterator::class, $members);

        $expected = [
            'canceled' => false,
            ['trial_ends', $start, '>='],
            ['trial_ends', $end, '<='],
            'last_trial_reminder IS NULL',
        ];
        $this->assertEquals($expected, $members->getQuery()->getWhere());
    }

    public function testGetEndedTrials(): void
    {
        $job = $this->getJob();

        $t = time();
        /** @var Iterator $members */
        $members = $job->getEndedTrials();

        $this->assertInstanceOf(Iterator::class, $members);

        $expected = [
            'canceled' => false,
            ['trial_ends', 0, '>'],
            ['trial_ends', $t, '<'],
            '(last_trial_reminder < trial_ends OR last_trial_reminder IS NULL)',
        ];
        $this->assertEquals($expected, $members->getQuery()->getWhere());
    }

    public function testSendTrialReminders(): void
    {
        $mailer = Mockery::mock(Mailer::class);
        $job = Mockery::mock('App\EntryPoint\CronJob\SendTrialReminders[getTrialsEndingSoon,getEndedTrials]', [$mailer, self::getService('test.tenant'), '']);
        $job->setStatsd(new StatsdClient());

        $member = Mockery::mock(Company::class.'[save]');
        $mailer->shouldReceive('sendToAdministrators')
            ->withArgs([
                $member,
                [
                    'subject' => 'Your Invoiced trial ends soon',
                ],
                'trial-will-end',
                [
                    'company' => null,
                    'dashboardUrl' => '',
                ],
            ])
            ->once();
        $member->shouldReceive('save')->once();

        $member2 = Mockery::mock(Company::class.'[save]');
        $mailer->shouldReceive('sendToAdministrators')
            ->withArgs([
                $member2,
                [
                    'subject' => 'Your Invoiced trial has ended',
                ],
                'trial-ended',
                [
                    'company' => null,
                    'dashboardUrl' => '',
                ],
            ])
            ->once();
        $member2->shouldReceive('save')->once();

        $job->shouldReceive('getTrialsEndingSoon')
            ->andReturn([$member]);

        $job->shouldReceive('getEndedTrials')
            ->andReturn([$member2]);

        $this->assertEquals([1, 1], $job->sendTrialReminders());

        $this->assertGreaterThan(0, $member->last_trial_reminder);
        $this->assertGreaterThan(0, $member2->last_trial_reminder);
    }

    private function getJob(): SendTrialReminders
    {
        $job = new SendTrialReminders(self::getService('test.mailer'), self::getService('test.tenant'), '');
        $job->setStatsd(new StatsdClient());

        return $job;
    }
}
