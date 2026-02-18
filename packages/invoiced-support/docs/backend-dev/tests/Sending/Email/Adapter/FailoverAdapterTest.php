<?php

namespace App\Tests\Sending\Email\Adapter;

use App\Sending\Email\Adapter\FailoverAdapter;
use App\Sending\Email\Exceptions\SendEmailException;
use App\Sending\Email\Interfaces\AdapterInterface;
use Mockery;
use Symfony\Component\RateLimiter\Storage\CacheStorage;

class FailoverAdapterTest extends AbstractAdapterTest
{
    public function testSendNoFailover(): void
    {
        $adapter1 = Mockery::mock(AdapterInterface::class);
        $adapter1->shouldReceive('isInvoicedService')->andReturn(false);
        $adapter1->shouldReceive('send')->once();

        $adapter2 = Mockery::mock(AdapterInterface::class);
        $adapter2->shouldReceive('isInvoicedService')->andReturn(true);
        $adapter2->shouldReceive('send')
            ->andThrow(new SendEmailException('fail'));

        $adapter = $this->makeAdapter([$adapter1, $adapter2]);

        $email = $this->getEmail();
        // The first adapter should succeed
        $adapter->send($email);
    }

    public function testSend(): void
    {
        parent::testSend();
    }

    protected function getAdapter(): FailoverAdapter
    {
        $adapter1 = Mockery::mock(AdapterInterface::class);
        $adapter1->shouldReceive('isInvoicedService')->andReturn(false);
        $adapter1->shouldReceive('send')
            ->andThrow(new SendEmailException('fail'))
            ->once();

        $adapter2 = Mockery::mock(AdapterInterface::class);
        $adapter2->shouldReceive('isInvoicedService')->andReturn(true);
        $adapter2->shouldReceive('send')->once();

        return $this->makeAdapter([$adapter1, $adapter2]);
    }

    private function makeAdapter(array $adapters): FailoverAdapter
    {
        $storage = new CacheStorage(self::getService('test.cache'));

        return new FailoverAdapter($adapters, $storage, self::getService('test.lock_factory'));
    }
}
