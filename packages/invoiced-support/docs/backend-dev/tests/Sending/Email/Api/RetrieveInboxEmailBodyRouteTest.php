<?php

namespace App\Tests\Sending\Email\Api;

use App\Sending\Email\Api\RetrieveInboxEmailBodyRoute;
use App\Sending\Email\Models\InboxEmail;
use App\Sending\Email\Storage\NullBodyStorage;
use App\Tests\AppTestCase;
use Mockery;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

class RetrieveInboxEmailBodyRouteTest extends AppTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasCompany();
        self::hasInbox();
        self::hasEmailThread();
        self::hasInboxEmail();
    }

    public function testRun(): void
    {
        $result = $this->runRequest();

        $this->assertEquals($result, [
            'text' => $this->getEmailBody(),
            'html' => $this->getEmailHtmlBody(),
            'text_parsed' => $this->getStrippedEmailBody(),
        ]);
    }

    private function runRequest(): array
    {
        $request = new Request([], [], [
            'inbox_id' => self::$inbox->id,
            'model_id' => self::$inboxEmail->id,
        ]);

        $requestStack = new RequestStack();
        $requestStack->push($request);

        $storage = Mockery::mock(NullBodyStorage::class);
        $storage->shouldReceive('retrieve')
            ->andReturn($this->getEmailBody())->once();
        $storage->shouldReceive('retrieve')
            ->andReturn($this->getEmailHtmlBody())->once();

        $route = new RetrieveInboxEmailBodyRoute(
            $storage,
        );
        $route->setModelClass(InboxEmail::class);
        $context = self::getService('test.api_runner')->validateRequest($request, $route->getDefinition());

        return $route->buildResponse($context);
    }

    private function getEmailBody(): string
    {
        return <<<HEREDOC
        test
        
        On Fri, Sep 18, 2020 at 3:55 PM Base Company <no-reply@invoiced.com> wrote:
        
        > test
        >
        HEREDOC;
    }

    private function getEmailHtmlBody(): string
    {
        return <<<HEREDOC
        <div>test</div>
        
        On Fri, Sep 18, 2020 at 3:55 PM Base Company <no-reply@invoiced.com> wrote:
        
        > <div>test</div>
        >
        HEREDOC;
    }

    private function getStrippedEmailBody(): string
    {
        return <<<HEREDOC
        test
        HEREDOC;
    }
}
