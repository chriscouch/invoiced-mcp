<?php

namespace App\Sending\Email\Models;

use App\AccountsPayable\Models\Vendor;
use App\AccountsReceivable\Models\Customer;
use App\Companies\Models\Member;
use App\Core\Authentication\Models\User;
use App\Core\Multitenant\Models\HasCustomerRestrictionsTrait;
use App\Core\Multitenant\Models\MultitenantModel;
use App\Core\Orm\Event\AbstractEvent;
use App\Core\Orm\Exception\ListenerException;
use App\Core\Orm\Property;
use App\Core\Orm\Query;
use App\Core\Orm\Traits\AutoTimestamps;
use App\Core\Orm\Type;
use App\Core\Utils\Enums\ObjectType;
use App\Notifications\Enums\NotificationEventType;
use App\Notifications\Libs\NotificationSpoolFacade;

/**
 * An EmailThread represents a collection of InboxEmails that are related to each either by a direct relationship
 * of one email being a reply to the previous, or by common attributes such as subject line, sender, and recipients.
 *
 * @property int             $id
 * @property Inbox           $inbox
 * @property int             $inbox_id
 * @property int|null        $customer_id
 * @property Customer|null   $customer
 * @property int|null        $vendor_id
 * @property Vendor|null     $vendor
 * @property User|null       $assignee
 * @property int|null        $assignee_id
 * @property string|null     $object_type
 * @property ObjectType|null $related_to_type
 * @property int|null        $related_to_id
 * @property string          $name
 * @property string          $status
 * @property int|null        $close_date
 * @property InboxEmail[]    $emails
 */
class EmailThread extends MultitenantModel
{
    use AutoTimestamps;
    use HasCustomerRestrictionsTrait;

    const STATUS_OPEN = 'open';
    const STATUS_PENDING = 'pending';
    const STATUS_CLOSED = 'closed';

    private int $cnt = 0;
    private bool $wasAssigned = false;

    protected static function getProperties(): array
    {
        return [
            'inbox' => new Property(
                required: true,
                belongs_to: Inbox::class,
            ),
            'inbox_id' => new Property(
                type: Type::INTEGER,
                required: true,
                relation: Inbox::class,
            ),
            'customer' => new Property(
                null: true,
                belongs_to: Customer::class,
            ),
            'vendor' => new Property(
                null: true,
                belongs_to: Vendor::class,
            ),
            'assignee' => new Property(
                null: true,
                belongs_to: User::class,
            ),
            'related_to_type' => new Property(
                type: Type::ENUM,
                null: true,
                in_array: false,
                enum_class: ObjectType::class,
            ),
            'related_to_id' => new Property(
                type: Type::INTEGER,
                null: true,
            ),
            'name' => new Property(
                type: Type::STRING,
                required: true,
            ),
            'status' => new Property(
                required: true,
                validate: ['enum', 'choices' => ['open', 'pending', 'closed']],
                default: self::STATUS_OPEN,
            ),
            'close_date' => new Property(
                type: Type::DATE_UNIX,
                null: true,
                validate: 'timestamp',
            ),
            'emails' => new Property(
                foreign_key: 'thread_id',
                has_many: InboxEmail::class,
            ),
            'notes' => new Property(
                foreign_key: 'thread_id',
                has_many: EmailThreadNote::class,
            ),
        ];
    }

    protected function initialize(): void
    {
        self::updating([self::class, 'protectCustomerVendor'], -512);
        self::saved([self::class, 'updatedAssignee']);
        self::saving([self::class, 'updatingAssignee']);
        parent::initialize();
    }

    public function toArray(): array
    {
        $result = parent::toArray();
        $result['object_type'] = $this->object_type;

        return $result;
    }

    public function getEmailListValue(): array
    {
        if (!isset($this->_relationships['emails']) || !is_array($this->_relationships['emails'])) {
            return [];
        }
        // we assure that items are sorted by id
        $emails = $this->_relationships['emails'];
        usort($emails, fn (InboxEmail $email1, InboxEmail $email2) => $email1->id <=> $email2->id);

        $emails = array_map(fn (InboxEmail $email) => $email->toArray(), $emails);

        return $emails;
    }

    public function setCnt(int $cnt): void
    {
        $this->cnt = $cnt;
    }

    public function getCntValue(): int
    {
        return $this->cnt;
    }

    public function getObjectTypeValue(): ?string
    {
        return $this->related_to_type?->typeName();
    }

    public static function customizeBlankQuery(Query $query): Query
    {
        return self::customizeBlankQueryRestrictions($query)
            ->sort('id DESC');
    }

    protected static function getCustomerPropertyName(): string
    {
        return 'customer_id';
    }

    public static function protectCustomerVendor(AbstractEvent $event): void
    {
        /** @var self $model */
        $model = $event->getModel();

        // Reassigning customer or vendor is not allowed when related to document is set
        if (!$model->ignoreUnsaved()->related_to_id) {
            return;
        }

        if ($model->dirty('customer_id', true)) {
            throw new ListenerException('Customer cannot be changed if the thread is related to document: '.$model->related_to_id, ['field' => 'customer']);
        }

        if ($model->dirty('vendor_id', true)) {
            throw new ListenerException('Vendor cannot be changed if the thread is related to document: '.$model->related_to_id, ['field' => 'vendor']);
        }
    }

    public static function updatingAssignee(AbstractEvent $event): void
    {
        /** @var self $model */
        $model = $event->getModel();
        $model->wasAssigned = $model->dirty('assignee_id') && $model->assignee_id;
    }

    public static function updatedAssignee(AbstractEvent $event): void
    {
        /** @var self $model */
        $model = $event->getModel();
        // we check null in updatingAssignee method, but we need this check, to satisfy phpStan
        if ($model->wasAssigned) {
            $member = Member::where('user_id', $model->assignee_id)->oneOrNull();
            if ($member instanceof Member) {
                NotificationSpoolFacade::get()->spool(NotificationEventType::ThreadAssigned, $model->tenant_id, $model->id, $member->id);
            }
        }
    }

    /**
     * Deletes the thread if it contains no emails.
     */
    public function deleteIfEmpty(): void
    {
        $numEmails = InboxEmail::queryWithoutMultitenancyUnsafe()
            ->where('thread_id', $this)
            ->count();
        if ($numEmails > 0) {
            return;
        }

        $this->delete();
    }
}
