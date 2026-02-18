<?php

namespace App\Tests\Notifications\Libs;

use App\Companies\Models\Company;
use App\Companies\Models\Member;
use App\Core\Authentication\Models\User;
use App\Core\Mailer\Mailer;
use App\Core\Statsd\StatsdClient;
use App\Notifications\Interfaces\NotificationEmailInterface;
use App\Notifications\Libs\NotificationEmailSender;
use App\Notifications\Models\NotificationEvent;
use App\Tests\AppTestCase;
use Mockery;

class NotificationEmailSenderTest extends AppTestCase
{
    private function getSender(Mailer $mailer): NotificationEmailSender
    {
        $sender = new NotificationEmailSender($mailer, self::getService('test.database'));
        $sender->setStatsd(new StatsdClient());

        return $sender;
    }

    /**
     * @doesNotPerformAssertions
     */
    public function testSendNoEvents(): void
    {
        $mailer = Mockery::mock(Mailer::class);
        $events = [];
        $member = new Member();
        $email = Mockery::mock(NotificationEmailInterface::class);

        $this->getSender($mailer)->send($events, $member, $email);
    }

    public function testSend(): void
    {
        $user = new User();
        $user->email = 'test@example.com';
        $member = Mockery::mock(Member::class)->makePartial();
        $member->shouldReceive('user')->andReturn($user);
        $company = new Company();
        $member->shouldReceive('tenant')->andReturn($company);

        $mailer = Mockery::mock(Mailer::class);
        $mailer->shouldReceive('sendToUser')
            ->withArgs(function ($arg1, $arg2, $arg3, $arg4) use ($user, $company) {
                $this->assertEquals($user, $arg1);
                $this->assertEquals(['subject' => 'test'], $arg2);
                $this->assertEquals('notifications/test', $arg3);
                $this->assertEquals([
                    'test' => true,
                    'tenant_id' => null,
                    '_moneyOptions' => $company->moneyFormat(),
                ], $arg4);

                return true;
            })->once();

        $events = [
            new NotificationEvent(['id' => -1]),
        ];

        $email = Mockery::mock(NotificationEmailInterface::class);
        $email->shouldReceive('getMessage')->andReturn(['subject' => 'test']);
        $email->shouldReceive('getTemplate')->andReturn('notifications/test');
        $email->shouldReceive('getVariables')->andReturn(['test' => true]);

        $this->getSender($mailer)->send($events, $member, $email);
    }
}
