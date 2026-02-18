<?php

namespace App\Tests\Core\Search;

use App\AccountsReceivable\Models\Customer;
use App\Core\Search\Driver\Database\DatabaseIndex;
use App\Tests\AppTestCase;
use SplFixedArray;

class DatabaseIndexTest extends AppTestCase
{
    private function getIndex(): DatabaseIndex
    {
        return new DatabaseIndex(Customer::class);
    }

    public function testGetName(): void
    {
        $index = $this->getIndex();
        $this->assertEquals(Customer::class, $index->getName());
    }

    /**
     * @doesNotPerformAssertions
     */
    public function testInsertDocument(): void
    {
        $index = $this->getIndex();
        $index->insertDocument('1', ['test' => true]);
    }

    /**
     * @doesNotPerformAssertions
     */
    public function testUpdateDocument(): void
    {
        $index = $this->getIndex();
        $index->updateDocument('1', ['test' => true]);
    }

    /**
     * @doesNotPerformAssertions
     */
    public function testDeleteDocument(): void
    {
        $index = $this->getIndex();
        $index->deleteDocument('1');
    }

    /**
     * @doesNotPerformAssertions
     */
    public function testRename(): void
    {
        $index = $this->getIndex();
        $index->rename('test');
    }

    /**
     * @doesNotPerformAssertions
     */
    public function testDelete(): void
    {
        $index = $this->getIndex();
        $index->delete();
    }

    public function testGetIds(): void
    {
        $index = $this->getIndex();

        $ids = $index->getIds();
        $this->assertInstanceOf(SplFixedArray::class, $ids);
        $this->assertEquals([], $ids->toArray());
    }
}
