<?php

namespace App\AccountsReceivable\Models;

use App\Chasing\Legacy\ChaseSchedule;
use App\Core\Multitenant\Models\MultitenantModel;
use App\PaymentProcessing\ValueObjects\RetrySchedule;
use App\Sending\Email\Models\Inbox;
use App\Core\Orm\Event\AbstractEvent;
use App\Core\Orm\Exception\ListenerException;
use App\Core\Orm\Property;
use App\Core\Orm\Type;

/**
 * @property int         $tenant_id
 * @property bool        $chase_new_invoices
 * @property string      $default_collection_mode
 * @property string|null $payment_terms
 * @property array       $aging_buckets
 * @property string      $aging_date
 * @property int|null    $default_template_id
 * @property string|null $default_theme_id
 * @property object|null $add_payment_plan_on_import
 * @property bool        $default_consolidated_invoicing
 * @property int|null    $unit_cost_precision
 * @property bool        $allow_chasing
 * @property array       $chase_schedule
 * @property int         $autopay_delay_days
 * @property array       $payment_retry_schedule
 * @property bool        $transactions_inherit_invoice_metadata
 * @property bool        $auto_apply_credits
 * @property bool        $saved_cards_require_cvc
 * @property bool        $debit_cards_only
 * @property string      $email_provider
 * @property string      $bcc
 * @property int|null    $reply_to_inbox_id
 * @property Inbox|null  $reply_to_inbox
 * @property Inbox|null  $inbox
 * @property int|null    $inbox_id
 * @property string      $tax_calculator
 * @property string      $default_customer_type
 */
class AccountsReceivableSettings extends MultitenantModel
{
    const COLLECTION_MODE_MANUAL = 'manual';
    const COLLECTION_MODE_AUTO = 'auto';

    private const CHASE_PROPERTIES = [
        'allow_chasing',
        'chase_schedule',
    ];

    private bool $_recalculateChase = false;

    protected static function getIDProperties(): array
    {
        return ['tenant_id'];
    }

    protected static function getProperties(): array
    {
        return [
            'chase_new_invoices' => new Property(
                type: Type::BOOLEAN,
                default: false,
            ),
            'default_collection_mode' => new Property(
                validate: ['enum', 'choices' => ['auto', 'manual']],
                default: self::COLLECTION_MODE_MANUAL,
            ),
            'payment_terms' => new Property(
                null: true,
                validate: ['string', 'min' => 1, 'max' => 255],
            ),
            'aging_buckets' => new Property(
                type: Type::ARRAY,
                default: [0, 8, 15, 31, 61],
            ),
            'aging_date' => new Property(
                validate: ['enum', 'choices' => ['date', 'due_date']],
                default: 'date',
            ),
            'default_template_id' => new Property(
                type: Type::INTEGER,
                null: true,
            ),
            'default_theme_id' => new Property(
                null: true,
            ),
            'add_payment_plan_on_import' => new Property(
                type: Type::OBJECT,
                null: true,
            ),
            'default_consolidated_invoicing' => new Property(
                type: Type::BOOLEAN,
                default: false,
            ),
            'unit_cost_precision' => new Property(
                type: Type::INTEGER,
                null: true,
            ),
            'allow_chasing' => new Property(
                type: Type::BOOLEAN,
                default: false,
            ),
            'chase_schedule' => new Property(
                type: Type::ARRAY,
                validate: ['callable', 'fn' => [ChaseSchedule::class, 'validate']],
                default: [],
            ),
            'autopay_delay_days' => new Property(
                type: Type::INTEGER,
                default: 0,
            ),
            'payment_retry_schedule' => new Property(
                type: Type::ARRAY,
                validate: ['callable', 'fn' => [RetrySchedule::class, 'validate']],
                default: [3, 5, 7],
            ),
            'transactions_inherit_invoice_metadata' => new Property(
                type: Type::BOOLEAN,
                default: false,
            ),
            'auto_apply_credits' => new Property(
                type: Type::BOOLEAN,
                default: false,
            ),
            'saved_cards_require_cvc' => new Property(
                type: Type::BOOLEAN,
            ),
            'debit_cards_only' => new Property(
                type: Type::BOOLEAN,
            ),
            'email_provider' => new Property(
                validate: ['enum', 'choices' => ['invoiced', 'null', 'smtp']],
                default: 'invoiced',
            ),
            'bcc' => new Property(),
            'inbox' => new Property(
                null: true,
                belongs_to: Inbox::class,
            ),
            'reply_to_inbox' => new Property(
                null: true,
                belongs_to: Inbox::class,
            ),
            'tax_calculator' => new Property(
                validate: ['enum', 'choices' => ['invoiced', 'avalara']],
                default: 'invoiced',
            ),
            'default_customer_type' => new Property(
                required: true,
                validate: ['enum', 'choices' => ['company', 'government', 'non_profit', 'person']],
                default: 'company',
            ),
        ];
    }

    protected function initialize(): void
    {
        self::saving([self::class, 'checkIfRecalculatingChase']);
        self::saved([self::class, 'recalculateChaseSchedules']);
        self::deleting(function (): never {
            throw new ListenerException('Deleting settings not permitted');
        });

        parent::initialize();
    }

    public static function checkIfRecalculatingChase(AbstractEvent $event): void
    {
        /** @var self $model */
        $model = $event->getModel();

        // check if the chasing settings are changing
        foreach (self::CHASE_PROPERTIES as $prop) {
            if ($model->$prop != $model->ignoreUnsaved()->$prop) {
                $model->_recalculateChase = true;
            }
        }
    }

    /**
     * Recalculates the chase schedules on any invoices after
     * changing the chasing schedule.
     */
    public static function recalculateChaseSchedules(AbstractEvent $event): void
    {
        /** @var self $model */
        $model = $event->getModel();

        if ($model->_recalculateChase) {
            self::getDriver()->getConnection(null)->update('Invoices', ['recalculate_chase' => true], [
                'tenant_id' => $model->id(),
            ]);
            $model->_recalculateChase = false;
        }
    }

    /**
     * Sets the chase_schedule property.
     *
     * @param array|string $schedule
     */
    protected function setChaseScheduleValue($schedule): array
    {
        if (is_string($schedule)) {
            $schedule = json_decode($schedule, true);
        }

        return ChaseSchedule::buildFromArray($schedule)->toArray(true);
    }
}
