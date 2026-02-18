<?php

namespace App\Tests\Sending\Email\Adapter;

use App\Sending\Email\Adapter\NullAdapter;

class NullAdapterTest extends AbstractAdapterTest
{
    protected function getAdapter(): NullAdapter
    {
        return new NullAdapter();
    }

    /**
     * @doesNotPerformAssertions
     */
    public function testSend(): void
    {
        parent::testSend();
    }
}
