<?php

namespace App\Sending\Email\Models;

use App\Core\Multitenant\Models\MultitenantModel;
use App\Core\Orm\Event\AbstractEvent;
use App\Core\Orm\Event\ModelUpdated;
use App\Core\Orm\Property;
use App\Core\Orm\Traits\AutoTimestamps;

/**
 * @property string      $id
 * @property string      $name
 * @property string      $type
 * @property string|null $language
 * @property string      $subject
 * @property string      $body
 * @property array       $options
 * @property string      $template_engine
 */
class EmailTemplate extends MultitenantModel
{
    use AutoTimestamps;

    /* Email Templates */

    const NEW_INVOICE = 'new_invoice_email';
    const UNPAID_INVOICE = 'unpaid_invoice_email';
    const LATE_PAYMENT_REMINDER = 'late_payment_reminder_email';
    const PAID_INVOICE = 'paid_invoice_email';
    const PAYMENT_PLAN = 'payment_plan_onboard_email';
    const PAYMENT_RECEIPT = 'payment_receipt_email';
    const REFUND = 'refund_email';
    const ESTIMATE = 'estimate_email';
    const CREDIT_NOTE = 'credit_note_email';
    const STATEMENT = 'statement_email';
    const AUTOPAY_FAILED = 'auto_payment_failed_email';
    const SUBSCRIPTION_CONFIRMATION = 'subscription_confirmation_email';
    const SUBSCRIPTION_CANCELED = 'subscription_canceled_email';
    const SUBSCRIPTION_BILLED_SOON = 'subscription_renews_soon_email';
    const SIGN_IN_LINK = 'sign_in_link_email';

    /* Email Template Types */

    const TYPE_INVOICE = 'invoice';
    const TYPE_CREDIT_NOTE = 'credit_note';
    const TYPE_PAYMENT_PLAN = 'payment_plan';
    const TYPE_ESTIMATE = 'estimate';
    const TYPE_SUBSCRIPTION = 'subscription';
    const TYPE_TRANSACTION = 'transaction';
    const TYPE_STATEMENT = 'statement';
    const TYPE_CHASING = 'chasing';
    const TYPE_SIGN_IN_LINK = 'sign_in_link';

    private static array $defaultOptionsById = [
        self::NEW_INVOICE => [
            EmailTemplateOption::SEND_ON_SUBSCRIPTION_INVOICE => true,
            EmailTemplateOption::SEND_ON_ISSUE => false,
            EmailTemplateOption::BUTTON_TEXT => 'View Invoice',
            EmailTemplateOption::SEND_REMINDER_DAYS => 0,
            EmailTemplateOption::ATTACH_PDF => true,
            EmailTemplateOption::ATTACH_SECONDARY_FILES => false,
        ],
        self::UNPAID_INVOICE => [
            EmailTemplateOption::BUTTON_TEXT => 'View Invoice',
            EmailTemplateOption::ATTACH_PDF => true,
            EmailTemplateOption::ATTACH_SECONDARY_FILES => false,
        ],
        self::LATE_PAYMENT_REMINDER => [
            EmailTemplateOption::BUTTON_TEXT => 'View Invoice',
            EmailTemplateOption::ATTACH_PDF => true,
            EmailTemplateOption::ATTACH_SECONDARY_FILES => false,
        ],
        self::PAID_INVOICE => [
            EmailTemplateOption::BUTTON_TEXT => '',
            EmailTemplateOption::ATTACH_PDF => true,
            EmailTemplateOption::ATTACH_SECONDARY_FILES => false,
            EmailTemplateOption::SEND_ONCE_PAID => false,
        ],
        self::PAYMENT_PLAN => [
            EmailTemplateOption::BUTTON_TEXT => 'Setup Payment Plan',
            EmailTemplateOption::SEND_REMINDER_DAYS => 0,
            EmailTemplateOption::SEND_ON_ISSUE => false,
        ],
        self::AUTOPAY_FAILED => [
            EmailTemplateOption::BUTTON_TEXT => 'Update Payment Info',
            EmailTemplateOption::SEND_ON_CHARGE => true,
        ],
        self::PAYMENT_RECEIPT => [
            EmailTemplateOption::ATTACH_PDF => true,
            EmailTemplateOption::SEND_ON_CHARGE => true,
        ],
        self::REFUND => [
            EmailTemplateOption::ATTACH_PDF => true,
            EmailTemplateOption::SEND_ON_CHARGE => true,
        ],
        self::STATEMENT => [
            EmailTemplateOption::BUTTON_TEXT => 'View Statement',
            EmailTemplateOption::ATTACH_PDF => true,
        ],
        self::ESTIMATE => [
            EmailTemplateOption::BUTTON_TEXT => 'View Estimate',
            EmailTemplateOption::ATTACH_PDF => false,
            EmailTemplateOption::ATTACH_SECONDARY_FILES => false,
            EmailTemplateOption::SEND_REMINDER_DAYS => 0,
            EmailTemplateOption::SEND_ON_ISSUE => false,
        ],
        self::CREDIT_NOTE => [
            EmailTemplateOption::BUTTON_TEXT => 'View Credit Note',
            EmailTemplateOption::ATTACH_PDF => true,
            EmailTemplateOption::ATTACH_SECONDARY_FILES => false,
            EmailTemplateOption::SEND_ON_ISSUE => false,
        ],
        self::SUBSCRIPTION_CONFIRMATION => [
            EmailTemplateOption::SEND_ON_SUBSCRIBE => false,
            EmailTemplateOption::BUTTON_TEXT => 'Manage Subscription',
        ],
        self::SUBSCRIPTION_BILLED_SOON => [
            EmailTemplateOption::BUTTON_TEXT => 'Manage Subscription',
            EmailTemplateOption::DAYS_BEFORE_BILLING => 0,
        ],
        self::SUBSCRIPTION_CANCELED => [
            EmailTemplateOption::SEND_ON_CANCELLATION => false,
        ],
        self::SIGN_IN_LINK => [
            EmailTemplateOption::BUTTON_TEXT => 'Sign In',
        ],
    ];

    private static array $defaultOptionsByType = [
        self::TYPE_CHASING => [
            EmailTemplateOption::BUTTON_TEXT => 'Pay Now',
            EmailTemplateOption::ATTACH_PDF => true,
        ],
        self::TYPE_INVOICE => [
            EmailTemplateOption::BUTTON_TEXT => 'View Invoice',
            EmailTemplateOption::ATTACH_PDF => true,
            EmailTemplateOption::ATTACH_SECONDARY_FILES => false,
        ],
        self::TYPE_CREDIT_NOTE => [
            EmailTemplateOption::BUTTON_TEXT => 'View Credit Note',
            EmailTemplateOption::ATTACH_PDF => true,
            EmailTemplateOption::ATTACH_SECONDARY_FILES => false,
        ],
        self::TYPE_PAYMENT_PLAN => [
            EmailTemplateOption::BUTTON_TEXT => 'Setup Payment Plan',
        ],
        self::TYPE_ESTIMATE => [
            EmailTemplateOption::BUTTON_TEXT => 'View Estimate',
            EmailTemplateOption::ATTACH_PDF => false,
            EmailTemplateOption::ATTACH_SECONDARY_FILES => false,
        ],
        self::TYPE_SUBSCRIPTION => [
            EmailTemplateOption::BUTTON_TEXT => 'Manage Subscription',
        ],
        self::TYPE_TRANSACTION => [
            EmailTemplateOption::ATTACH_PDF => true,
        ],
        self::TYPE_STATEMENT => [
            EmailTemplateOption::BUTTON_TEXT => 'View Statement',
            EmailTemplateOption::ATTACH_PDF => true,
        ],
        self::SIGN_IN_LINK => [
            EmailTemplateOption::BUTTON_TEXT => 'Sign in',
        ],
    ];

    private static array $availableVariablesById = [
        /* Invoice Templates */

        self::NEW_INVOICE => [
            // company
            '{{company_name}}',
            '{{company_username}}',
            '{{{company_address}}}',
            '{{company_email}}',
            // customer
            '{{customer_name}}',
            '{{customer_contact_name}}',
            '{{customer_number}}',
            '{{{customer_address}}}',
            '{{customer}}',
            // invoice
            '{{invoice_number}}',
            '{{invoice_date}}',
            '{{due_date}}',
            '{{payment_terms}}',
            '{{purchase_order}}',
            '{{total}}',
            '{{balance}}',
            '{{discounts}}',
            '{{notes}}',
            '{{invoice}}',
            '{{url}}',
            '{{payment_url}}',
            '{{{view_invoice_button}}}',
            '{{attempt_count}}',
            '{{next_payment_attempt}}',
        ],
        self::UNPAID_INVOICE => [
            // company
            '{{company_name}}',
            '{{company_username}}',
            '{{{company_address}}}',
            '{{company_email}}',
            // customer
            '{{customer_name}}',
            '{{customer_contact_name}}',
            '{{customer_number}}',
            '{{{customer_address}}}',
            '{{customer}}',
            // invoice
            '{{invoice_number}}',
            '{{invoice_date}}',
            '{{due_date}}',
            '{{payment_terms}}',
            '{{purchase_order}}',
            '{{total}}',
            '{{balance}}',
            '{{discounts}}',
            '{{notes}}',
            '{{invoice}}',
            '{{url}}',
            '{{payment_url}}',
            '{{{view_invoice_button}}}',
            '{{attempt_count}}',
            '{{next_payment_attempt}}',
        ],
        self::LATE_PAYMENT_REMINDER => [
            // company
            '{{company_name}}',
            '{{company_username}}',
            '{{{company_address}}}',
            '{{company_email}}',
            // customer
            '{{customer_name}}',
            '{{customer_contact_name}}',
            '{{customer_number}}',
            '{{{customer_address}}}',
            '{{customer}}',
            // invoice
            '{{invoice_number}}',
            '{{invoice_date}}',
            '{{due_date}}',
            '{{payment_terms}}',
            '{{purchase_order}}',
            '{{total}}',
            '{{balance}}',
            '{{discounts}}',
            '{{notes}}',
            '{{invoice}}',
            '{{url}}',
            '{{payment_url}}',
            '{{{view_invoice_button}}}',
            '{{attempt_count}}',
            '{{next_payment_attempt}}',
        ],
        self::PAID_INVOICE => [
            // company
            '{{company_name}}',
            '{{company_username}}',
            '{{{company_address}}}',
            '{{company_email}}',
            // customer
            '{{customer_name}}',
            '{{customer_contact_name}}',
            '{{customer_number}}',
            '{{{customer_address}}}',
            '{{customer}}',
            // invoice
            '{{invoice_number}}',
            '{{invoice_date}}',
            '{{due_date}}',
            '{{payment_terms}}',
            '{{purchase_order}}',
            '{{total}}',
            '{{balance}}',
            '{{discounts}}',
            '{{notes}}',
            '{{invoice}}',
            '{{url}}',
            '{{payment_url}}',
            '{{{view_invoice_button}}}',
            '{{attempt_count}}',
            '{{next_payment_attempt}}',
        ],
        self::PAYMENT_PLAN => [
            // company
            '{{company_name}}',
            '{{company_username}}',
            '{{{company_address}}}',
            '{{company_email}}',
            // customer
            '{{customer_name}}',
            '{{customer_contact_name}}',
            '{{customer_number}}',
            '{{{customer_address}}}',
            '{{customer}}',
            // invoice
            '{{invoice_number}}',
            '{{invoice_date}}',
            '{{due_date}}',
            '{{total}}',
            '{{balance}}',
            '{{discounts}}',
            '{{notes}}',
            '{{invoice}}',
            '{{url}}',
            '{{payment_url}}',
            '{{{view_invoice_button}}}',
        ],
        self::AUTOPAY_FAILED => [
            // company
            '{{company_name}}',
            '{{company_username}}',
            '{{{company_address}}}',
            '{{company_email}}',
            // customer
            '{{customer_name}}',
            '{{customer_contact_name}}',
            '{{customer_number}}',
            '{{{customer_address}}}',
            '{{customer}}',
            // invoice
            '{{invoice_number}}',
            '{{invoice_date}}',
            '{{due_date}}',
            '{{payment_terms}}',
            '{{purchase_order}}',
            '{{total}}',
            '{{balance}}',
            '{{discounts}}',
            '{{notes}}',
            '{{invoice}}',
            '{{url}}',
            '{{payment_url}}',
            '{{attempt_count}}',
            '{{next_payment_attempt}}',
            // payments
            '{{{update_payment_info_button}}}',
            '{{payment_amount}}',
        ],

        /* Estimate Templates */

        self::ESTIMATE => [
            // company
            '{{company_name}}',
            '{{company_username}}',
            '{{{company_address}}}',
            '{{company_email}}',
            // customer
            '{{customer_name}}',
            '{{customer_contact_name}}',
            '{{customer_number}}',
            '{{{customer_address}}}',
            '{{customer}}',
            // estimate
            '{{estimate_number}}',
            '{{estimate_date}}',
            '{{expiration_date}}',
            '{{payment_terms}}',
            '{{purchase_order}}',
            '{{total}}',
            '{{discounts}}',
            '{{notes}}',
            '{{estimate}}',
            '{{url}}',
            '{{{view_estimate_button}}}',
        ],

        /* Credit Note Templates */

        self::CREDIT_NOTE => [
            // company
            '{{company_name}}',
            '{{company_username}}',
            '{{{company_address}}}',
            '{{company_email}}',
            // customer
            '{{customer_name}}',
            '{{customer_contact_name}}',
            '{{customer_number}}',
            '{{{customer_address}}}',
            '{{customer}}',
            // credit note
            '{{credit_note_number}}',
            '{{credit_note_date}}',
            '{{total}}',
            '{{discounts}}',
            '{{notes}}',
            '{{credit_note}}',
            '{{url}}',
            '{{{view_credit_note_button}}}',
        ],

        /* Transaction Templates */

        self::PAYMENT_RECEIPT => [
            // company
            '{{company_name}}',
            '{{company_username}}',
            '{{{company_address}}}',
            '{{company_email}}',
            // customer
            '{{customer_name}}',
            '{{customer_contact_name}}',
            '{{customer_number}}',
            '{{{customer_address}}}',
            '{{customer}}',
            // transaction
            '{{invoice_number}}',
            '{{payment_date}}',
            '{{payment_method}}',
            '{{payment_amount}}',
            '{{payment_source}}',
        ],

        self::REFUND => [
            // company
            '{{company_name}}',
            '{{company_username}}',
            '{{{company_address}}}',
            '{{company_email}}',
            // customer
            '{{customer_name}}',
            '{{customer_contact_name}}',
            '{{customer_number}}',
            '{{{customer_address}}}',
            '{{customer}}',
            // transaction
            '{{invoice_number}}',
            '{{payment_date}}',
            '{{payment_method}}',
            '{{payment_amount}}',
            '{{payment_source}}',
            '{{refund_date}}',
            '{{refund_amount}}',
        ],

        /* Statement Templates */

        self::STATEMENT => [
            // company
            '{{company_name}}',
            '{{customer_contact_name}}',
            '{{company_username}}',
            '{{{company_address}}}',
            '{{company_email}}',
            // customer
            '{{customer_name}}',
            '{{customer_number}}',
            '{{{customer_address}}}',
            '{{customer}}',
            // statement
            '{{statement_start_date}}',
            '{{statement_end_date}}',
            '{{statement_balance}}',
            '{{statement_credit_balance}}',
            '{{statement_url}}',
            '{{{view_statement_button}}}',
        ],

        /* Subscription Templates */

        self::SUBSCRIPTION_CONFIRMATION => [
            // company
            '{{company_name}}',
            '{{company_username}}',
            '{{{company_address}}}',
            '{{company_email}}',
            // customer
            '{{customer_name}}',
            '{{customer_contact_name}}',
            '{{customer_number}}',
            '{{{customer_address}}}',
            '{{customer}}',
            // subscription
            '{{name}}',
            '{{recurring_total}}',
            '{{frequency}}',
            '{{start_date}}',
            '{{url}}',
            '{{{manage_subscription_button}}}',
        ],
        self::SUBSCRIPTION_CANCELED => [
            // company
            '{{company_name}}',
            '{{company_username}}',
            '{{{company_address}}}',
            '{{company_email}}',
            // customer
            '{{customer_name}}',
            '{{customer_contact_name}}',
            '{{customer_number}}',
            '{{{customer_address}}}',
            '{{customer}}',
            // subscription
            '{{name}}',
            '{{recurring_total}}',
            '{{frequency}}',
            '{{start_date}}',
        ],
        self::SUBSCRIPTION_BILLED_SOON => [
            // company
            '{{company_name}}',
            '{{company_username}}',
            '{{{company_address}}}',
            '{{company_email}}',
            // customer
            '{{customer_name}}',
            '{{customer_contact_name}}',
            '{{customer_number}}',
            '{{{customer_address}}}',
            '{{customer}}',
            // subscription
            '{{name}}',
            '{{recurring_total}}',
            '{{frequency}}',
            '{{start_date}}',
            '{{url}}',
            '{{{manage_subscription_button}}}',
            '{{time_until_renewal}}',
        ],
    ];

    private static array $availableVariablesByType = [
        self::TYPE_CHASING => [
            // company
            '{{company_name}}',
            '{{company_username}}',
            '{{{company_address}}}',
            '{{company_email}}',
            // customer
            '{{customer_name}}',
            '{{customer_contact_name}}',
            '{{customer_number}}',
            '{{{customer_address}}}',
            '{{customer_payment_terms}}',
            '{{customer}}',
            // statement
            '{{account_balance}}',
            '{{past_due_account_balance}}',
            '{{invoice_numbers}}',
            '{{invoice_dates}}',
            '{{invoice_due_dates}}',
            '{{{customer_portal_button}}}',
        ],
        self::TYPE_INVOICE => [
            // company
            '{{company_name}}',
            '{{company_username}}',
            '{{{company_address}}}',
            '{{company_email}}',
            // customer
            '{{customer_name}}',
            '{{customer_contact_name}}',
            '{{customer_number}}',
            '{{{customer_address}}}',
            '{{customer}}',
            // invoice
            '{{invoice_number}}',
            '{{invoice_date}}',
            '{{due_date}}',
            '{{payment_terms}}',
            '{{purchase_order}}',
            '{{total}}',
            '{{balance}}',
            '{{discounts}}',
            '{{notes}}',
            '{{invoice}}',
            '{{url}}',
            '{{payment_url}}',
            '{{{view_invoice_button}}}',
            '{{attempt_count}}',
            '{{next_payment_attempt}}',
        ],
        self::TYPE_CREDIT_NOTE => [
            // company
            '{{company_name}}',
            '{{company_username}}',
            '{{{company_address}}}',
            '{{company_email}}',
            // customer
            '{{customer_name}}',
            '{{customer_contact_name}}',
            '{{customer_number}}',
            '{{{customer_address}}}',
            '{{customer}}',
            // credit note
            '{{credit_note_number}}',
            '{{credit_note_date}}',
            '{{total}}',
            '{{discounts}}',
            '{{notes}}',
            '{{credit_note}}',
            '{{url}}',
            '{{{view_credit_note_button}}}',
        ],
        self::TYPE_PAYMENT_PLAN => [
            // company
            '{{company_name}}',
            '{{company_username}}',
            '{{{company_address}}}',
            '{{company_email}}',
            // customer
            '{{customer_name}}',
            '{{customer_contact_name}}',
            '{{customer_number}}',
            '{{{customer_address}}}',
            '{{customer}}',
            // invoice
            '{{invoice_number}}',
            '{{invoice_date}}',
            '{{due_date}}',
            '{{total}}',
            '{{balance}}',
            '{{discounts}}',
            '{{notes}}',
            '{{invoice}}',
            '{{url}}',
            '{{payment_url}}',
            '{{{view_invoice_button}}}',
        ],
        self::TYPE_ESTIMATE => [
            // company
            '{{company_name}}',
            '{{company_username}}',
            '{{{company_address}}}',
            '{{company_email}}',
            // customer
            '{{customer_name}}',
            '{{customer_contact_name}}',
            '{{customer_number}}',
            '{{{customer_address}}}',
            '{{customer}}',
            // estimate
            '{{estimate_number}}',
            '{{estimate_date}}',
            '{{expiration_date}}',
            '{{payment_terms}}',
            '{{purchase_order}}',
            '{{total}}',
            '{{discounts}}',
            '{{notes}}',
            '{{estimate}}',
            '{{url}}',
            '{{{view_estimate_button}}}',
        ],
        self::TYPE_SUBSCRIPTION => [
            // company
            '{{company_name}}',
            '{{company_username}}',
            '{{{company_address}}}',
            '{{company_email}}',
            // customer
            '{{customer_name}}',
            '{{customer_contact_name}}',
            '{{customer_number}}',
            '{{{customer_address}}}',
            '{{customer}}',
            // subscription
            '{{name}}',
            '{{recurring_total}}',
            '{{frequency}}',
            '{{start_date}}',
            '{{url}}',
            '{{{manage_subscription_button}}}',
        ],
        self::TYPE_TRANSACTION => [
            // company
            '{{company_name}}',
            '{{company_username}}',
            '{{{company_address}}}',
            '{{company_email}}',
            // customer
            '{{customer_name}}',
            '{{customer_contact_name}}',
            '{{customer_number}}',
            '{{{customer_address}}}',
            '{{customer}}',
            // transaction
            '{{invoice_number}}',
            '{{payment_date}}',
            '{{payment_method}}',
            '{{payment_amount}}',
            '{{payment_source}}',
        ],
        self::TYPE_STATEMENT => [
            // company
            '{{company_name}}',
            '{{customer_contact_name}}',
            '{{company_username}}',
            '{{{company_address}}}',
            '{{company_email}}',
            // customer
            '{{customer_name}}',
            '{{customer_number}}',
            '{{{customer_address}}}',
            '{{customer}}',
            // statement
            '{{statement_start_date}}',
            '{{statement_end_date}}',
            '{{statement_balance}}',
            '{{statement_credit_balance}}',
            '{{statement_url}}',
            '{{{view_statement_button}}}',
        ],
        self::TYPE_SIGN_IN_LINK => [
            // company
            '{{company_name}}',
            '{{customer_contact_name}}',
            '{{sign_in_url}}',
            '{{{sign_in_button}}}',
        ],
    ];

    public static array $names = [
        self::NEW_INVOICE => 'New Invoice',
        self::UNPAID_INVOICE => 'Invoice Reminder',
        self::LATE_PAYMENT_REMINDER => 'Past Due Invoice',
        self::PAID_INVOICE => 'Thank You',
        self::PAYMENT_PLAN => 'Payment Plan',
        self::CREDIT_NOTE => 'Credit Note',
        self::ESTIMATE => 'Estimate',
        self::AUTOPAY_FAILED => 'Failed AutoPay Attempt',
        self::PAYMENT_RECEIPT => 'Payment Receipt',
        self::REFUND => 'Refund',
        self::STATEMENT => 'Statement',
        self::SUBSCRIPTION_CONFIRMATION => 'Subscription Confirmation',
        self::SUBSCRIPTION_CANCELED => 'Subscription Canceled',
        self::SUBSCRIPTION_BILLED_SOON => 'Subscription Billed Soon',
    ];

    public static array $types = [
        self::NEW_INVOICE => self::TYPE_INVOICE,
        self::UNPAID_INVOICE => self::TYPE_INVOICE,
        self::LATE_PAYMENT_REMINDER => self::TYPE_INVOICE,
        self::PAID_INVOICE => self::TYPE_INVOICE,
        self::PAYMENT_PLAN => self::TYPE_PAYMENT_PLAN,
        self::CREDIT_NOTE => self::TYPE_CREDIT_NOTE,
        self::ESTIMATE => self::TYPE_ESTIMATE,
        self::AUTOPAY_FAILED => self::TYPE_INVOICE,
        self::PAYMENT_RECEIPT => self::TYPE_TRANSACTION,
        self::REFUND => self::TYPE_TRANSACTION,
        self::STATEMENT => self::TYPE_STATEMENT,
        self::SUBSCRIPTION_CONFIRMATION => self::TYPE_SUBSCRIPTION,
        self::SUBSCRIPTION_CANCELED => self::TYPE_SUBSCRIPTION,
        self::SUBSCRIPTION_BILLED_SOON => self::TYPE_SUBSCRIPTION,
    ];

    private static array $subjects = [
        self::NEW_INVOICE => 'Invoice from {{company_name}}: {{invoice_number}}',
        self::UNPAID_INVOICE => 'Invoice from {{company_name}}: {{invoice_number}}',
        self::LATE_PAYMENT_REMINDER => 'Past Due - Invoice from {{company_name}}: {{invoice_number}}',
        self::PAID_INVOICE => 'Thank You - Invoice from {{company_name}}: {{invoice_number}}',
        self::PAYMENT_PLAN => 'Action Required - Payment plan from {{company_name}}: {{invoice_number}}',
        self::ESTIMATE => 'Estimate from {{company_name}}: {{estimate_number}}',
        self::CREDIT_NOTE => 'Credit Note from {{company_name}}: {{credit_note_number}}',
        self::PAYMENT_RECEIPT => 'Receipt for your payment to {{company_name}}',
        self::REFUND => 'Refund from {{company_name}}',
        self::STATEMENT => 'Account statement from {{company_name}}',
        self::SUBSCRIPTION_CONFIRMATION => 'You are now subscribed to {{name}} from {{company_name}}',
        self::SUBSCRIPTION_CANCELED => 'Your subscription to {{name}} has been canceled',
        self::AUTOPAY_FAILED => 'Your recent payment to {{company_name}} failed',
        self::SUBSCRIPTION_BILLED_SOON => 'You are about to be charged for a subscription',
    ];

    private ?array $_options = null;

    public static function make(int $tenantId, string $id): self
    {
        return new self(['tenant_id' => $tenantId, 'id' => $id]);
    }

    protected static function getIDProperties(): array
    {
        return ['tenant_id', 'id'];
    }

    protected static function getProperties(): array
    {
        return [
            'id' => new Property(
                required: true,
            ),
            'name' => new Property(
                required: true,
            ),
            'type' => new Property(
                required: true,
                validate: ['enum', 'choices' => ['invoice', 'credit_note', 'payment_plan', 'estimate', 'subscription', 'transaction', 'statement', 'chasing']],
            ),
            'language' => new Property(
                null: true,
                validate: ['string', 'min' => 2, 'max' => 2],
            ),
            'subject' => new Property(
                required: true,
            ),
            'body' => new Property(
                required: true,
            ),
            'template_engine' => new Property(
                validate: ['enum', 'choices' => ['mustache', 'twig']],
                default: 'twig',
            ),
        ];
    }

    protected function initialize(): void
    {
        self::creating([self::class, 'setIdNameAndType']);
        self::created([self::class, 'saveOptionsUpdate']);
        self::updated([self::class, 'saveOptionsUpdate']);
        self::deleted([self::class, 'cleanUpOptions']);

        parent::initialize();
    }

    public function toArray(): array
    {
        $result = parent::toArray();
        $result['options'] = $this->options;

        return $result;
    }

    //
    // Hooks
    //

    /**
     * Sets the name, if one is not given.
     */
    public static function setIdNameAndType(AbstractEvent $event): void
    {
        /** @var self $model */
        $model = $event->getModel();
        if (!$model->id) {
            $model->id = uniqid();
        } else {
            if (!$model->name) {
                $model->name = self::$names[$model->id];
            }

            if (isset(self::$types[$model->id])) {
                $model->type = self::$types[$model->id];
            }
        }
    }

    /**
     * Saves options after an update.
     */
    public static function saveOptionsUpdate(AbstractEvent $event, string $eventName): void
    {
        /** @var self $model */
        $model = $event->getModel();
        $isUpdate = ModelUpdated::getName() == $eventName;
        $model->saveOptions($isUpdate);
    }

    /**
     * Deletes options after deleting.
     */
    public static function cleanUpOptions(AbstractEvent $event): void
    {
        /** @var self $model */
        $model = $event->getModel();
        self::getDriver()->getConnection(null)->delete('EmailTemplateOptions', [
            'tenant_id' => $model->tenant_id,
            'template' => $model->id,
        ]);
    }

    //
    // Mutators
    //

    /**
     * Sets the options property.
     */
    protected function setOptionsValue(?array $options): ?array
    {
        $this->_options = $options;

        return $options;
    }

    //
    // Accessors
    //

    /**
     * Gets the options property.
     */
    protected function getOptionsValue(): array
    {
        if (!is_array($this->_options)) {
            // add in default options
            if (isset(self::$defaultOptionsById[$this->id])) {
                $options = self::$defaultOptionsById[$this->id];
            } elseif (isset(self::$defaultOptionsByType[$this->type])) {
                $options = self::$defaultOptionsByType[$this->type];
            } else {
                $options = [];
            }

            // load any saved options
            $models = EmailTemplateOption::where('tenant_id', $this->tenant_id)
                ->where('template', $this->id)
                ->all();

            foreach ($models as $option) {
                $options[$option->option] = $option->value;
            }

            $this->_options = $options;
        }

        return $this->_options;
    }

    /**
     * Gets the subject property.
     */
    protected function getSubjectValue(?string $subject): string
    {
        // use default if custom subject was not set
        $subject = (string) $subject;
        if (0 === strlen($subject)) {
            $subject = (string) array_value(self::$subjects, $this->id);
        }

        // remove any extra whitespace
        return trim($subject);
    }

    /**
     * Gets the body property.
     */
    protected function getBodyValue(?string $body): string
    {
        // load standard template from the file system if the message body was not customized
        $body = (string) $body;
        if (0 === strlen($body)) {
            $assetsDir = dirname(__DIR__, 4).'/templates';
            $templateExtension = $this->template_engine ?: 'mustache';
            $filename = $assetsDir.'/emailContent/'.$this->id.'.'.$templateExtension;
            if (file_exists($filename)) {
                $body = (string) file_get_contents($filename);
            }
        }

        // remove any extra whitespace
        return trim($body);
    }

    //
    // Helpers
    //

    /**
     * Gets the available variable names for this template.
     *
     * @param bool $withBraces when true, includes the mustaches
     */
    public function getAvailableVariables(bool $withBraces = true): array
    {
        $available = [];
        if (isset(self::$availableVariablesById[$this->id])) {
            $available = self::$availableVariablesById[$this->id];
        } elseif (isset(self::$availableVariablesByType[$this->type])) {
            $available = self::$availableVariablesByType[$this->type];
        }

        if (!$withBraces) {
            foreach ($available as &$variable) {
                $variable = str_replace(['{', '}'], ['', ''], $variable);
            }
        }

        return $available;
    }

    /**
     * Gets the value of an option.
     */
    public function getOption(string $option): mixed
    {
        $options = $this->options;
        if (array_key_exists($option, $options)) {
            return $options[$option];
        }

        return null;
    }

    /**
     * Sets the raw subject for this template.
     */
    public function setSubject(string $subject): void
    {
        // do not set the subject to an empty string as this will
        // cause the default subject to be used instead of the one
        // saved on the template
        if (strlen($subject) > 0) {
            $this->subject = $subject;
        }
    }

    /**
     * Sets the body to be used with this template.
     */
    public function setBody(string $body): void
    {
        // do not set the body to an empty string as this will
        // cause the default body to be used instead of the one
        // saved on the template
        if (strlen($body) > 0) {
            $this->body = $body;
        }
    }

    /**
     * Gets the body to be used with this template, and
     * ensures that a button variable is included
     * (i.e. {{{view_invoice_button}}}, if the template supports a button.
     */
    public function getBodyWithButton(): string
    {
        $body = $this->body;

        // ensure that a button is included in the template
        if ($this->getOption(EmailTemplateOption::BUTTON_TEXT)) {
            // get the button variable name
            $buttonVariable = false;
            foreach ($this->getAvailableVariables() as $variable) {
                if (str_contains($variable, '_button')) {
                    $buttonVariable = $variable;

                    // convert to Twig syntax
                    if ('twig' == $this->template_engine) {
                        $buttonVariable = str_replace(['{{{', '}}}'], ['{{', '|raw}}'], $buttonVariable);
                    }

                    break;
                }
            }

            if ($buttonVariable && !str_contains($body, $buttonVariable)) {
                $body .= "\n\n$buttonVariable";
            }
        }

        return $body;
    }

    /**
     * Saves the template options.
     */
    private function saveOptions(bool $isUpdate = false): void
    {
        $options = $this->options;
        $saved = [];

        foreach ($options as $k => $v) {
            $option = EmailTemplateOption::where('tenant_id', $this->tenant_id)
                ->where('template', $this->id)
                ->where('option', $k)
                ->oneOrNull();

            if (!$option) {
                $option = new EmailTemplateOption();
            }

            $option->tenant_id = $this->tenant_id;
            $option->template = $this->id;
            $option->option = $k;
            $option->value = $v;

            $option->save();
            $saved[] = $k;
        }

        // delete any options that were not saved (for updates)
        if ($isUpdate) {
            $query = self::getDriver()->getConnection(null)->createQueryBuilder()
                ->delete('EmailTemplateOptions')
                ->andWhere('tenant_id = :tenantId')
                ->setParameter('tenantId', $this->tenant_id)
                ->andWhere('template = :template')
                ->setParameter('template', $this->id);

            if (count($saved) > 0) {
                $in = [];
                foreach ($saved as $option) {
                    $in[] = "'$option'";
                }
                $in = implode(',', $in);
                $query->andWhere("`option` NOT IN ($in)");
            }

            $query->executeStatement();
        }
    }
}
