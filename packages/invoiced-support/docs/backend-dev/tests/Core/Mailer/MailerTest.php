<?php

namespace App\Tests\Core\Mailer;

use App\Core\Mailer\EmailBlockList;
use App\Core\Mailer\EmailBlockReason;
use App\Core\Mailer\Mailer;
use App\Core\Queue\Queue;
use App\Tests\AppTestCase;
use Mockery;

class MailerTest extends AppTestCase
{
    public function testSend(): void
    {
        $blockList = Mockery::mock(EmailBlockList::class);
        $blockList->shouldReceive('isBlocked')
            ->with('test@blocked.com')
            ->andReturn(EmailBlockReason::PermanentBounce);
        $blockList->shouldReceive('isBlocked')
            ->with('test@not.blocked.com')
            ->andReturnNull();

        $queue = Mockery::mock(Queue::class);
        $mailer = new Mailer($queue, $blockList);
        $mailer->send([
            'to' => [
                ['email' => 'test@blocked.com'],
            ],
        ]);
        $queue->shouldNotHaveReceived('enqueue');

        $queue->shouldReceive('enqueue')
            ->withArgs(function ($job, $payload) use ($mailer) {
                $m = $mailer->decompressMessage($payload['m']);

                return 1 === count($m['to']) && 'test@not.blocked.com' === $m['to'][0]['email'];
            })
            ->once();
        $mailer = new Mailer($queue, $blockList);
        $mailer->send([
            'to' => [
                ['email' => 'test@blocked.com'],
                ['email' => 'test@not.blocked.com'],
            ],
        ]);
    }
}
