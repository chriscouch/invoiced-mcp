<?php

namespace App\Chasing\Models;

use App\AccountsPayable\Models\Bill;
use App\AccountsPayable\Models\VendorCredit;
use App\AccountsReceivable\Libs\CustomerPermissionHelper;
use App\AccountsReceivable\Models\Customer;
use App\Companies\Models\Member;
use App\Core\Authentication\Models\User;
use App\Core\Multitenant\Models\MultitenantModel;
use App\Core\Multitenant\TenantContextFacade;
use App\Core\Orm\ACLModelRequester;
use App\Core\Orm\Event\AbstractEvent;
use App\Core\Orm\Exception\ListenerException;
use App\Core\Orm\Property;
use App\Core\Orm\Query;
use App\Core\Orm\Traits\AutoTimestamps;
use App\Core\Orm\Type;
use App\Core\Utils\ModelNormalizer;
use App\ActivityLog\Enums\EventType;
use App\ActivityLog\Interfaces\EventObjectInterface;
use App\ActivityLog\Libs\EventSpoolFacade;
use App\ActivityLog\Traits\EventModelTrait;
use App\ActivityLog\ValueObjects\PendingEvent;
use App\Metadata\Libs\RestrictionQueryBuilder;
use App\Notifications\Enums\NotificationEventType;
use App\Notifications\Libs\NotificationSpoolFacade;

/**
 * @property int               $id
 * @property Customer|null     $customer
 * @property int|null          $customer_id
 * @property string            $name
 * @property string            $action
 * @property int               $due_date
 * @property int|null          $user_id
 * @property int|null          $chase_step_id
 * @property bool              $complete
 * @property int|null          $completed_date
 * @property int|null          $completed_by_user_id
 * @property Bill|null         $bill
 * @property VendorCredit|null $vendor_credit
 */
class Task extends MultitenantModel implements EventObjectInterface
{
    use AutoTimestamps;
    use EventModelTrait;

    private bool $wasAssigned = false;
    private bool $wasCompleted = false;
    private ?string $mostRecentNote;
    private array $aging;

    protected static function getProperties(): array
    {
        return [
            'customer' => new Property(
                null: true,
                belongs_to: Customer::class,
            ),
            'name' => new Property(
                required: true,
            ),
            'action' => new Property(
                required: true,
            ),
            'due_date' => new Property(
                type: Type::DATE_UNIX,
                required: true,
            ),
            'user_id' => new Property(
                type: Type::INTEGER,
                null: true,
                relation: User::class,
            ),
            'chase_step_id' => new Property(
                type: Type::INTEGER,
                null: true,
                relation: ChasingCadenceStep::class,
            ),
            'complete' => new Property(
                type: Type::BOOLEAN,
                default: false,
            ),
            'completed_date' => new Property(
                type: Type::DATE_UNIX,
                null: true,
            ),
            'completed_by_user_id' => new Property(
                type: Type::INTEGER,
                null: true,
                relation: User::class,
            ),
            'bill' => new Property(
                type: Type::INTEGER,
                null: true,
                belongs_to: Bill::class,
            ),
            'vendor_credit' => new Property(
                type: Type::INTEGER,
                null: true,
                belongs_to: VendorCredit::class,
            ),
        ];
    }

    protected function initialize(): void
    {
        parent::initialize();

        self::saving([self::class, 'setCompletedDate']);
        self::updated([self::class, 'recordEvent']);
        self::saving([self::class, 'verifyCustomer']);
        self::saving([self::class, 'updatingAssignee']);
        self::saved([self::class, 'updatedAssignee']);
    }

    public static function customizeBlankQuery(Query $query): Query
    {
        // Limit the result set for the member's customer restrictions.
        $requester = ACLModelRequester::get();
        if ($requester instanceof Member) {
            $tenant = TenantContextFacade::get()->get();
            if (Member::CUSTOM_FIELD_RESTRICTION == $requester->restriction_mode) {
                if ($restrictions = $requester->restrictions()) {
                    $restrictionQueryBuilder = new RestrictionQueryBuilder($tenant, $restrictions);
                    $restrictionQueryBuilder->addToOrmQuery('customer_id', $query);
                }
            } elseif (Member::OWNER_RESTRICTION == $requester->restriction_mode) {
                $query->where('customer_id IN (SELECT id FROM Customers WHERE tenant_id='.$tenant->id().' AND owner_id='.$requester->user_id.')');
            }
        }

        return $query;
    }

    public function toArray(): array
    {
        $result = parent::toArray();

        if (isset($this->aging)) {
            $result['aging'] = $this->aging;
        }

        if (isset($this->mostRecentNote)) {
            $result['most_recent_note'] = $this->mostRecentNote;
        }

        return $result;
    }

    /**
     * Sets the completed date when the task is marked complete.
     */
    public static function setCompletedDate(AbstractEvent $event): void
    {
        /** @var self $task */
        $task = $event->getModel();
        $task->wasCompleted = $task->complete && !$task->ignoreUnsaved()->complete;
        if ($task->wasCompleted) {
            $task->completed_date = time();
        }
    }

    /**
     * Creates an event when the task is marked complete.
     */
    public static function recordEvent(AbstractEvent $event): void
    {
        /** @var self $model */
        $model = $event->getModel();

        if ($model->wasCompleted) {
            $pendingEvent = new PendingEvent(
                object: $model,
                type: EventType::TaskCompleted
            );
            EventSpoolFacade::get()->enqueue($pendingEvent);
        }
    }

    public function user(): ?User
    {
        return $this->relation('user_id');
    }

    public function setUser(?User $user): void
    {
        if ($user) {
            $this->user_id = (int) $user->id();
            $this->setRelation('user_id', $user);
        } else {
            $this->user_id = null;
        }
    }

    public function setCompletedByUser(?User $user): void
    {
        if ($user) {
            $this->completed_by_user_id = (int) $user->id();
            $this->setRelation('completed_by_user_id', $user);
        } else {
            $this->completed_by_user_id = null;
        }
    }

    /**
     * Used to set the most recent note in the list tasks API.
     */
    public function setMostRecentNote(?string $note): void
    {
        $this->mostRecentNote = $note;
    }

    /**
     * Used to set the aging in the list tasks API.
     */
    public function setAging(array $aging): void
    {
        $this->aging = $aging;
    }

    public function getEventAssociations(): array
    {
        return [
            ['customer', $this->customer_id],
        ];
    }

    public function getEventObject(): array
    {
        return ModelNormalizer::toArray($this, expand: ['customer']);
    }

    public static function verifyCustomer(AbstractEvent $event): void
    {
        /** @var self $model */
        $model = $event->getModel();
        if (!$model->customer_id || !$model->dirty('customer_id', true)) {
            return;
        }

        // verify the customer exists
        $customer = $model->customer;
        if (!$customer instanceof Customer) {
            throw new ListenerException('No such customer: '.$model->customer_id, ['field' => 'customer_id']);
        }
    }

    public static function updatingAssignee(AbstractEvent $event): void
    {
        /** @var self $model */
        $model = $event->getModel();
        $model->wasAssigned = $model->dirty('user_id') && $model->user_id;

        // validate that the user exists
        $model->validateUser('user_id');
        $model->validateUser('completed_by_user_id');

        // validate customer permissions
        $customer = $model->customer;
        $user = $model->user();
        if ($customer instanceof Customer && $user instanceof User) {
            $member = Member::getForUser($user);
            if (!$member || !CustomerPermissionHelper::canSeeCustomer($customer, $member)) {
                throw new ListenerException('The task cannot be assigned to this user because they do not have permission to see this customer.');
            }
        }
    }

    public static function updatedAssignee(AbstractEvent $event): void
    {
        /** @var self $model */
        $model = $event->getModel();
        if ($model->wasAssigned) {
            $member = Member::where('user_id', $model->user_id)->oneOrNull();
            if ($member instanceof Member) {
                NotificationSpoolFacade::get()->spool(NotificationEventType::TaskAssigned, $model->tenant_id, $model->id, $member->id);
            }
        }
    }

    private function validateUser(string $field): void
    {
        if (!$this->$field || !$this->dirty($field, true)) {
            return;
        }

        // verify the user exists
        $user = $this->relation($field);
        if (!$user instanceof User) {
            throw new ListenerException('No such user: '.$this->$field, ['field' => $field]);
        }

        // verify the user is a member
        if (!$this->tenant()->isMember($user)) {
            throw new ListenerException('User is not a member of this company: '.$this->$field, ['field' => $field]);
        }
    }
}
