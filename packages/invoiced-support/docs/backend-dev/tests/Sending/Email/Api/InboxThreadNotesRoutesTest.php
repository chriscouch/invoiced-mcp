<?php

namespace App\Tests\Sending\Email\Api;

use App\Core\Authentication\Libs\UserContext;
use App\Core\RestApi\Libs\ApiCache;
use App\Core\RestApi\Routes\AbstractApiRoute;
use App\Core\Utils\SimpleCache;
use App\Sending\Email\Api\CreateInboxThreadNoteRoute;
use App\Sending\Email\Api\EditInboxThreadNoteRoute;
use App\Sending\Email\Api\ListInboxThreadNotesRoute;
use App\Sending\Email\Models\EmailThread;
use App\Sending\Email\Models\EmailThreadNote;
use App\Sending\Email\Models\Inbox;
use App\Tests\AppTestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

class InboxThreadNotesRoutesTest extends AppTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasCompany();
    }

    public function testRun(): void
    {
        /** @var UserContext $userContext */
        $userContext = AppTestCase::getService('test.user_context');

        /** @var Inbox $inbox */
        $inbox = Inbox::one();
        $thread = new EmailThread();
        $thread->inbox = $inbox;
        $thread->name = 'Thread';
        $thread->saveOrFail();

        $note1 = new EmailThreadNote();
        $note1->thread = $thread;
        $note1->note = 'note1';
        $note1->saveOrFail();

        $result = $this->runRequest($inbox, $thread, ['incoming' => true]);
        $this->assertCount(1, $result);
        $this->assertEquals($note1->id, $result[0]->id);

        $requestStack = $this->buildRequest($inbox, $thread, null, [], [
            'note' => 'test',
        ]);
        $route = new CreateInboxThreadNoteRoute($userContext);
        $route->setModelClass(EmailThreadNote::class);
        $note2 = $this->runRoute($route, $requestStack->getCurrentRequest()); /* @phpstan-ignore-line */

        $result = $this->runRequest($inbox, $thread, ['incoming' => true]);
        $this->assertCount(2, $result);
        $this->assertEquals($note2->id, $result[1]->id);
        $this->assertEquals($note2->note, 'test');
        $this->assertEquals($note1->id, $result[0]->id);

        $requestStack = $this->buildRequest($inbox, $thread, $note2->id, [], [
            'note' => 'test2',
        ]);
        $route = new EditInboxThreadNoteRoute();
        $route->setModelClass(EmailThreadNote::class);
        $note2 = $this->runRoute($route, $requestStack->getCurrentRequest()); /* @phpstan-ignore-line */

        $result = $this->runRequest($inbox, $thread, ['incoming' => true]);
        $this->assertCount(2, $result);
        $this->assertEquals($note2->id, $result[1]->id);
        $this->assertEquals($note2->note, 'test2');
        $this->assertEquals($note1->id, $result[0]->id);
    }

    private function runRequest(Inbox $inbox, EmailThread $thread, array $query = []): mixed
    {
        $requestStack = $this->buildRequest($inbox, $thread, null, $query);

        $route = new ListInboxThreadNotesRoute(new ApiCache(new ArrayAdapter(), new SimpleCache(new ArrayAdapter())));
        $route->setModelClass(EmailThreadNote::class);

        return $this->runRoute($route, $requestStack->getCurrentRequest()); /* @phpstan-ignore-line */
    }

    private function runRoute(AbstractApiRoute $route, Request $request): mixed
    {
        $context = self::getService('test.api_runner')->validateRequest($request, $route->getDefinition());

        return $route->buildResponse($context);
    }

    private function buildRequest(Inbox $inbox, EmailThread $thread, ?int $model = null, array $query = [], array $params = []): RequestStack
    {
        $request = new Request([], []);
        $request->attributes->set('inbox_id', $inbox->id());
        $request->attributes->set('thread_id', $thread->id());
        if ($model) {
            $request->attributes->set('model_id', $model);
        }
        foreach ($query as $key => $item) {
            $request->query->set($key, $item);
        }
        foreach ($params as $key => $item) {
            $request->request->set($key, $item);
        }
        $requestStack = new RequestStack();
        $requestStack->push($request);

        return $requestStack;
    }
}
