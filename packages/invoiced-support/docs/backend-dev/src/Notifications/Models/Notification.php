<?php

namespace App\Notifications\Models;

use App\Core\Authentication\Models\User;
use App\Core\Multitenant\Models\MultitenantModel;
use App\Core\Orm\Property;
use App\Core\Orm\Type;
use App\ActivityLog\Enums\EventType;
use App\Notifications\ValueObjects\Condition;
use App\Notifications\ValueObjects\Rule;

/**
 * Represents a notification rule that is stored in the DB.
 *
 * @property int      $id
 * @property string   $event
 * @property bool     $enabled
 * @property string   $medium
 * @property int|null $user_id
 * @property string   $match_mode
 * @property string   $conditions
 */
class Notification extends MultitenantModel
{
    const EMITTER_EMAIL = 'email';
    const EMITTER_SLACK = 'slack';
    const EMITTER_NULL = 'null';

    const MAX_CONDITIONS = 10;

    public static array $notifyByDefault = [
        EventType::ChargeFailed->value => true,
        EventType::EstimateApproved->value => true,
        EventType::EstimateCommented->value => true,
        EventType::EstimateViewed->value => true,
        EventType::InvoiceCommented->value => true,
        EventType::InvoicePaid->value => true,
        EventType::InvoicePaymentExpected->value => true,
        EventType::InvoiceViewed->value => true,
        EventType::PaymentCreated->value => true,
        EventType::SubscriptionCanceled->value => true,
    ];

    protected static function getProperties(): array
    {
        return [
            'event' => new Property(
                required: true,
            ),
            'enabled' => new Property(
                type: Type::BOOLEAN,
            ),
            'medium' => new Property(
                required: true,
                default: self::EMITTER_EMAIL,
            ),
            'user_id' => new Property(
                type: Type::INTEGER,
                mutable: Property::MUTABLE_CREATE_ONLY,
                null: true,
                relation: User::class,
            ),
            'match_mode' => new Property(
                type: Type::STRING,
                validate: ['enum', 'choices' => ['any', 'all']],
                default: Rule::MATCH_ANY,
            ),
            'conditions' => new Property(
                type: Type::STRING,
            ),
        ];
    }

    /**
     * Creates a notification rule from this model.
     */
    public function toRule(): Rule
    {
        if (!$this->conditions) {
            return new Rule($this->match_mode, []);
        }

        $conditions = (array) json_decode($this->conditions);
        $conditions = array_splice($conditions, 0, self::MAX_CONDITIONS);
        foreach ($conditions as &$condition) {
            $condition = Condition::fromObject($condition);
        }

        return new Rule($this->match_mode, $conditions);
    }

    //
    // Relationships
    //

    /**
     * Gets the user.
     */
    public function user(): ?User
    {
        return $this->relation('user_id');
    }
}
