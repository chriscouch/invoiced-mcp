<?php

namespace App\Sending\Email\Models;

use App\Core\RestApi\Traits\ApiObjectTrait;
use App\Core\Multitenant\Models\MultitenantModel;
use App\Core\Orm\Event\AbstractEvent;
use App\Core\Orm\Property;
use App\Core\Orm\Traits\AutoTimestamps;
use App\Core\Orm\Type;
use App\Core\Utils\InfuseUtility as Utility;
use App\ActivityLog\Interfaces\EventObjectInterface;
use App\ActivityLog\Traits\EventObjectTrait;

/**
 * @deprecated
 *
 * @property string      $id
 * @property int|null    $customer
 * @property int|null    $customer_id
 * @property string      $state
 * @property string|null $reject_reason
 * @property string      $email
 * @property string      $template
 * @property string      $subject
 * @property string      $message
 * @property string      $tracking_id
 * @property int         $opens
 * @property array       $opens_detail
 */
class Email extends MultitenantModel implements EventObjectInterface
{
    use ApiObjectTrait;
    use AutoTimestamps;
    use EventObjectTrait;

    private ?string $_message = null;
    private ?array $_opensDetail = null;

    protected static function getProperties(): array
    {
        return [
            'id' => new Property(
                mutable: Property::MUTABLE_CREATE_ONLY,
                required: true,
            ),
            'customer_id' => new Property(
                mutable: Property::MUTABLE_CREATE_ONLY,
                null: true,
                in_array: false,
            ),
            'state' => new Property(
                validate: ['enum', 'choices' => ['sent', 'scheduled', 'queued', 'invalid', 'deferred', 'rejected', 'soft-bounced', 'bounced']],
            ),
            'reject_reason' => new Property(
                null: true,
            ),
            'email' => new Property(),
            'template' => new Property(),
            'subject' => new Property(),
            'tracking_id' => new Property(
                mutable: Property::MUTABLE_CREATE_ONLY,
                required: true,
                in_array: false,
            ),
            'opens' => new Property(
                type: Type::INTEGER,
            ),
        ];
    }

    protected function initialize(): void
    {
        self::creating([self::class, 'genId']);
        self::created([self::class, 'saveMessage']);

        parent::initialize();
    }

    public function toArray(): array
    {
        $result = parent::toArray();
        $result['customer'] = $this->customer;
        $result['object'] = $this->object;
        $result['message'] = $this->message;
        $result['opens_detail'] = $this->opens_detail;

        return $result;
    }

    /**
     * Generates a model ID.
     */
    public static function genId(AbstractEvent $event): void
    {
        // if no ID was given then generate a random one
        /** @var self $model */
        $model = $event->getModel();
        if (!$model->id) {
            $model->id = strtolower(Utility::guid(false));
        }
    }

    /**
     * Saves the message body in a compressed format.
     */
    public static function saveMessage(AbstractEvent $event): void
    {
        $email = $event->getModel();
        if (!$email->_message) {
            return;
        }

        $sql = 'UPDATE Emails SET message_compressed=COMPRESS(:message) WHERE id=:id';

        self::getDriver()->getConnection(null)->executeStatement($sql, ['message' => $email->_message, 'id' => $email->id()]);
    }

    //
    // Accessors
    //

    protected function getCustomerValue(): ?int
    {
        return $this->customer_id;
    }

    /**
     * Gets the message property.
     */
    public function getMessageValue(): string
    {
        if (!$this->_message && $this->hasId()) {
            $this->_message = self::getDriver()->getConnection(null)->fetchOne('SELECT UNCOMPRESS(message_compressed) FROM Emails WHERE id=?', [$this->id()]);
        }

        return (string) $this->_message;
    }

    /**
     * Gets the opens detail property.
     */
    public function getOpensDetailValue(): array
    {
        // get the 5 most recent opens
        if (!$this->_opensDetail) {
            $this->_opensDetail = EmailOpen::where('email_id', $this->id())
                ->sort('timestamp DESC')
                ->first(5);

            foreach ($this->_opensDetail as &$open) {
                $open = $open->toArray();
            }
        }

        return $this->_opensDetail;
    }

    //
    // Mutators
    //

    /**
     * Sets the message property.
     */
    public function setMessageValue(?string $value): ?string
    {
        $this->_message = $value;

        return $value;
    }
}
