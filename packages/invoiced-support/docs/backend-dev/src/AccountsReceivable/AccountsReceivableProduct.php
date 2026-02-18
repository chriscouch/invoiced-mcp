<?php

namespace App\AccountsReceivable;

use App\AccountsReceivable\Models\AccountsReceivableSettings;
use App\Companies\Models\Company;
use App\Core\Entitlements\Exception\InstallProductException;
use App\Core\Entitlements\Interfaces\InstallProductInterface;
use App\Core\Entitlements\Traits\InstallProductTrait;
use App\PaymentProcessing\Models\PaymentMethod;
use App\Sending\Email\Models\Inbox;
use Carbon\CarbonImmutable;
use Doctrine\DBAL\Connection;

class AccountsReceivableProduct implements InstallProductInterface
{
    use InstallProductTrait;

    const DEFAULT_ROLES = [
        [
            'id' => 'employee',
            'name' => 'Employee',
            'permissions' => [
                'customers_create',
                'customers_edit',
                'customers_delete',
                'invoices_create',
                'invoices_issue',
                'invoices_edit',
                'invoices_void',
                'invoices_delete',
                'credit_notes_create',
                'credit_notes_issue',
                'credit_notes_edit',
                'credit_notes_void',
                'credit_notes_delete',
                'estimates_create',
                'estimates_issue',
                'estimates_edit',
                'estimates_void',
                'estimates_delete',
                'emails_send',
                'text_messages_send',
                'letters_send',
                'payments_create',
                'payments_edit',
                'payments_delete',
                'charges_create',
                'refunds_create',
                'credits_create',
                'credits_apply',
                'imports_create',
                'reports_create',
                'settings_edit',
                'catalog_edit',
                'comments_create',
                'notes_create',
                'notes_edit',
                'notes_delete',
                'notifications_edit',
                'subscriptions_create',
                'subscriptions_edit',
                'subscriptions_delete',
                'tasks_create',
                'tasks_edit',
                'tasks_delete',
            ],
        ],
        [
            'id' => 'ar_specialist',
            'name' => 'A/R Specialist',
            'permissions' => [
                'imports_create',
                'reports_create',
                'customers_create',
                'customers_edit',
                'customers_delete',
                'invoices_create',
                'invoices_issue',
                'invoices_edit',
                'invoices_void',
                'invoices_delete',
                'credit_notes_create',
                'credit_notes_issue',
                'credit_notes_edit',
                'credit_notes_void',
                'credit_notes_delete',
                'estimates_create',
                'estimates_issue',
                'estimates_edit',
                'estimates_void',
                'estimates_delete',
                'emails_send',
                'text_messages_send',
                'letters_send',
                'payments_create',
                'payments_edit',
                'payments_delete',
                'charges_create',
                'refunds_create',
                'credits_create',
                'credits_apply',
                'comments_create',
                'notes_create',
                'notes_edit',
                'notes_delete',
                'subscriptions_create',
                'subscriptions_edit',
                'subscriptions_delete',
                'tasks_create',
                'tasks_edit',
                'tasks_delete',
            ],
        ],
        [
            'id' => 'read_only',
            'name' => 'Read-Only',
            'permissions' => [],
        ],
    ];

    public const AUTO_NUMBER_SEQUENCES = [
        'credit_note' => 'CN-%05d',
        'customer' => 'CUST-%05d',
        'estimate' => 'EST-%05d',
        'invoice' => 'INV-%05d',
        'customer_payment_batch' => 'BAT-%05d',
    ];

    private const PAYMENT_TERMS = [
        ['name' => 'NET 7', 'due_in_days' => 7],
        ['name' => 'NET 15', 'due_in_days' => 15],
        ['name' => 'NET 30', 'due_in_days' => 30],
        ['name' => 'NET 45', 'due_in_days' => 45],
        ['name' => 'NET 60', 'due_in_days' => 60],
        ['name' => 'NET 90', 'due_in_days' => 90],
        ['name' => 'Due on Receipt', 'due_in_days' => 0],
    ];

    public function __construct(private Connection $database)
    {
    }

    public function install(Company $company): void
    {
        $this->createSettings($company);
        $this->createRoles($company, self::DEFAULT_ROLES);
        $this->createPaymentMethods($company);
        $this->createAutoNumberSequences($company, self::AUTO_NUMBER_SEQUENCES);
        $this->createInbox($company);
        $this->createPaymentTerms($company, self::PAYMENT_TERMS);
    }

    /**
     * Creates settings for the company.
     */
    public function createSettings(Company $company): void
    {
        // skip if settings already exists
        $existing = $this->database->fetchOne('SELECT COUNT(*) FROM AccountsReceivableSettings WHERE tenant_id=:tenantId', [
            'tenantId' => $company->id,
        ]);
        if ($existing > 0) {
            return;
        }

        $settings = new AccountsReceivableSettings();
        $settings->tenant_id = (int) $company->id();
        if ($company->test_mode) {
            $settings->email_provider = 'null';
        }
        if (!$settings->save()) {
            throw new InstallProductException('Could not create settings: '.$settings->getErrors());
        }
    }

    /**
     * Creates payment methods for the company.
     */
    private function createPaymentMethods(Company $company): void
    {
        foreach (PaymentMethod::METHODS as $method) {
            $id = $method->toString();
            // skip if payment method already exists
            $existing = $this->database->fetchOne('SELECT COUNT(*) FROM PaymentMethods WHERE tenant_id=:tenantId AND id=:id', [
                'tenantId' => $company->id,
                'id' => $id,
            ]);
            if ($existing > 0) {
                continue;
            }

            $method = new PaymentMethod();
            $method->tenant_id = (int) $company->id();
            $method->id = $id;
            $method->enabled = false;
            if (!$method->save()) {
                throw new InstallProductException('Could not create payment methods: '.$method->getErrors());
            }
        }
    }

    /**
     * Creates inbox on company creation.
     */
    private function createInbox(Company $company): void
    {
        // skip if inbox already exists
        if ($company->accounts_receivable_settings->inbox) {
            return;
        }

        $inbox = new Inbox();
        $inbox->tenant_id = (int) $company->id();
        if (!$inbox->save()) {
            throw new InstallProductException('Could not create email inbox: '.$inbox->getErrors());
        }

        // set the default reply to address to the inbox
        $company->accounts_receivable_settings->inbox = $inbox;
        $company->accounts_receivable_settings->reply_to_inbox = $inbox;
        $company->accounts_receivable_settings->save();
    }

    /**
     * Creates payment terms on company creation.
     */
    private function createPaymentTerms(Company $company, array $terms): void
    {
        foreach ($terms as $row) {
            // skip if payment terms already exists
            $existing = $this->database->fetchOne('SELECT COUNT(*) FROM PaymentTerms WHERE tenant_id=:tenantId AND name=:name', [
                'tenantId' => $company->id,
                'name' => $row['name'],
            ]);
            if ($existing > 0) {
                continue;
            }

            // bypass the model layer because unique name validation will fail
            $this->database->insert('PaymentTerms', array_merge([
                'tenant_id' => $company->id,
                'active' => true,
                'created_at' => CarbonImmutable::now()->toDateTimeString(),
            ], $row));
        }
    }
}
