<?php

namespace App\Tests\AccountsReceivable\Models;

use App\AccountsReceivable\Models\Note;
use App\Core\Authentication\Models\User;
use App\Core\Orm\Model;
use App\Core\Utils\ModelNormalizer;
use App\ActivityLog\Enums\EventType;
use App\Tests\ModelTestCase;

/**
 * @extends ModelTestCase<Note>
 */
class NoteTest extends ModelTestCase
{
    private static Note $note;
    private static Note $note2;
    private static User $ogUser;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::hasCustomer();
        self::hasInvoice();

        self::$ogUser = self::getService('test.user_context')->get();
    }

    public function assertPostConditions(): void
    {
        self::getService('test.user_context')->set(self::$ogUser);
    }

    protected function getModelCreate(): Model
    {
        $note = new Note();
        $note->invoice = self::$invoice;
        $note->notes = 'Testing';
        self::$note = $note;

        return $note;
    }

    protected function getExpectedToArray($model, array &$output): array
    {
        $user = self::getService('test.user_context')->get()->toArray();

        return [
            'created_at' => self::$note->created_at,
            'customer' => self::$customer->id(),
            'customer_id' => self::$customer->id(),
            'id' => self::$note->id,
            'invoice' => self::$invoice->id(),
            'invoice_id' => self::$invoice->id(),
            'notes' => 'Testing',
            'object' => 'note',
            'updated_at' => self::$note->updated_at,
            'user' => $user,
            'user_id' => $user['id'],
        ];
    }

    protected function getModelEdit($model): Note
    {
        $model->notes = 'New notes';

        return $model;
    }

    public function testEventAssociations(): void
    {
        $note = new Note();
        $note->customer_id = 1234;
        $note->invoice_id = 4567;

        $this->assertEquals([
            ['customer', 1234],
            ['invoice', 4567],
        ], $note->getEventAssociations());
    }

    public function testEventObject(): void
    {
        $note = new Note();
        $note->customer = self::$customer;

        $this->assertEquals(array_merge($note->toArray(), [
            'customer' => ModelNormalizer::toArray(self::$customer),
            'user' => null,
        ]), $note->getEventObject());
    }

    public function testCreate(): void
    {
        parent::testCreate();
        $this->assertEquals(self::$company->id(), self::$note->tenant_id);
        $this->assertEquals(self::getService('test.user_context')->get()->id(), self::$note->user_id);
        $this->assertEquals(self::$customer->id(), self::$note->customer_id);

        self::$note2 = new Note();
        self::$note2->customer = self::$customer;
        self::$note2->notes = 'Testing';
        $this->assertTrue(self::$note2->save());
        $this->assertEquals(self::$company->id(), self::$note2->tenant_id);
        $this->assertEquals(self::getService('test.user_context')->get()->id(), self::$note2->user_id);
    }

    public function testCreateNoUser(): void
    {
        $user = new User(['id' => -1]);
        self::getService('test.user_context')->set($user);

        $note = new Note();
        $note->invoice = self::$invoice;
        $note->user = null;
        $note->notes = 'Testing';
        $this->assertTrue($note->save());
        $this->assertEquals(self::$company->id(), $note->tenant_id);
        $this->assertNull($note->user_id);
    }

    /**
     * @depends testCreate
     */
    public function testEventCreated(): void
    {
        $this->assertHasEvent(self::$note, EventType::NoteCreated);
    }

    /**
     * @depends testEdit
     */
    public function testEventEdited(): void
    {
        $this->assertHasEvent(self::$note, EventType::NoteUpdated);
    }

    /**
     * @depends testDelete
     */
    public function testEventDeleted(): void
    {
        $this->assertHasEvent(self::$note, EventType::NoteDeleted);
    }
}
