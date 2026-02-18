<?php

namespace App\AccountsPayable;

use App\AccountsPayable\Ledger\AccountsPayableLedger;
use App\AccountsPayable\Models\AccountsPayableSettings;
use App\Companies\Models\Company;
use App\Core\Entitlements\Exception\InstallProductException;
use App\Core\Entitlements\Interfaces\InstallProductInterface;
use App\Core\Entitlements\Traits\InstallProductTrait;
use App\Sending\Email\Models\Inbox;
use Doctrine\DBAL\Connection;

class AccountsPayableProduct implements InstallProductInterface
{
    use InstallProductTrait;

    const DEFAULT_ROLES = [
        [
            'id' => 'ap_specialist',
            'name' => 'A/P Specialist',
            'permissions' => [
                'imports_create',
                'reports_create',
                'bills_create',
                'bills_edit',
                'bills_delete',
                'emails_send',
                'vendor_payments_create',
                'vendor_payments_edit',
                'vendor_payments_delete',
                'vendors_create',
                'vendors_edit',
                'vendors_delete',
            ],
        ],
        [
            'id' => 'ap_approver',
            'name' => 'A/P Approver',
            'permissions' => [
                'emails_send',
            ],
        ],
    ];

    public const AUTO_NUMBER_SEQUENCES = [
        'vendor' => 'VEND-%05d',
        'vendor_payment' => 'PAY-%05d',
        'vendor_payment_batch' => 'BAT-%05d',
    ];

    public function __construct(
        private AccountsPayableLedger $accountsPayableLedger,
        private Connection $database,
    ) {
    }

    public function install(Company $company): void
    {
        $this->createSettings($company);
        $this->createRoles($company, self::DEFAULT_ROLES);
        $this->createLedger($company);
        $this->createAutoNumberSequences($company, self::AUTO_NUMBER_SEQUENCES);
        $this->createInbox($company);
    }

    /**
     * Creates settings for the company.
     */
    public function createSettings(Company $company): void
    {
        // skip if settings already exists
        $existing = $this->database->fetchOne('SELECT COUNT(*) FROM AccountsPayableSettings WHERE tenant_id=:tenantId', [
            'tenantId' => $company->id,
        ]);
        if ($existing > 0) {
            return;
        }

        $settings = new AccountsPayableSettings();
        $settings->tenant_id = (int) $company->id();
        if (!$settings->save()) {
            throw new InstallProductException('Could not create settings: '.$settings->getErrors());
        }
    }

    /**
     * Creates a new A/P ledger for the company.
     */
    private function createLedger(Company $company): void
    {
        $this->accountsPayableLedger->getLedger($company);
    }

    /**
     * Creates inbox on company creation.
     */
    private function createInbox(Company $company): void
    {
        // skip if inbox already exists
        if ($company->accounts_payable_settings->inbox) {
            return;
        }

        $inbox = new Inbox();
        $inbox->tenant_id = (int) $company->id();
        if (!$inbox->save()) {
            throw new InstallProductException('Could not create email inbox: '.$inbox->getErrors());
        }

        // set the default reply to address to the inbox
        $company->accounts_payable_settings->inbox = $inbox;
        $company->accounts_payable_settings->save();
    }
}
