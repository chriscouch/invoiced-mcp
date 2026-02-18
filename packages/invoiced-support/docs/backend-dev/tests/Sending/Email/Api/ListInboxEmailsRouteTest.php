<?php

namespace App\Tests\Sending\Email\Api;

use App\Core\RestApi\Libs\ApiCache;
use App\Core\Utils\SimpleCache;
use App\Sending\Email\Api\ListInboxEmailsRoute;
use App\Sending\Email\Models\EmailThread;
use App\Sending\Email\Models\Inbox;
use App\Sending\Email\Models\InboxEmail;
use App\Tests\AppTestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpFoundation\Request;

class ListInboxEmailsRouteTest extends AppTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasCompany();
    }

    public function testRun(): void
    {
        $inbox = Inbox::one();
        $thread = new EmailThread();
        $thread->inbox = $inbox;
        $thread->name = 'Thread';
        $thread->saveOrFail();
        $mail1 = new InboxEmail();
        $mail1->thread = $thread;
        $mail1->incoming = false;
        $mail1->saveOrFail();
        $mail2 = new InboxEmail();
        $mail2->thread = $thread;
        $mail2->incoming = false;
        $mail2->saveOrFail();
        $incoming = new InboxEmail();
        $incoming->thread = $thread;
        $incoming->incoming = true;
        $incoming->saveOrFail();

        $result = $this->runRequest($inbox, ['filter' => ['incoming' => 0]]);
        $this->assertCount(2, $result);
        $this->assertEquals($mail2->id, $result[0]->id);
        $this->assertEquals($mail1->id, $result[1]->id);
        $result = $this->runRequest($inbox, ['filter' => ['incoming' => 1]]);
        $this->assertCount(1, $result);
        $this->assertEquals($incoming->id, $result[0]->id);
        $result = $this->runRequest($inbox);
        $this->assertCount(3, $result);
    }

    private function runRequest(Inbox $inbox, array $query = []): array
    {
        $request = new Request([], []);
        $request->attributes->set('model_id', $inbox->id);
        foreach ($query as $key => $item) {
            $request->query->set($key, $item);
        }

        $route = new ListInboxEmailsRoute(self::getService('test.database'), new ApiCache(new ArrayAdapter(), new SimpleCache(new ArrayAdapter())));
        $route->setModelClass(InboxEmail::class);
        $context = self::getService('test.api_runner')->validateRequest($request, $route->getDefinition());
        $route->parseListParameters($request);

        return $route->buildResponse($context);
    }
}
