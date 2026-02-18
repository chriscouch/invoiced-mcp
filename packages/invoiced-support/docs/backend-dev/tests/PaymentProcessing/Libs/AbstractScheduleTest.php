<?php

namespace App\Tests\PaymentProcessing\Libs;

use App\AccountsReceivable\Enums\InvoiceStatus;
use App\AccountsReceivable\Models\AccountsReceivableSettings;
use App\AccountsReceivable\Models\Customer;
use App\AccountsReceivable\Models\Invoice;
use App\Companies\Models\Company;
use App\Tests\AppTestCase;

abstract class AbstractScheduleTest extends AppTestCase
{
    protected function getInvoice(array $values = []): Invoice
    {
        // mocking the invoice
        $invoice = new class($values) extends Invoice {
            private Customer $_customer;
            private Company $_company;
            public bool $autopay = true;
            public bool $draft = false;
            public bool $paid = false;
            public bool $closed = false;
            public float $balance = 100;
            public string $status = InvoiceStatus::NotSent->value;

            public function __construct(array $values = [])
            {
                // mopcking the company
                $company = new class() extends Company {
                    public AccountsReceivableSettings $accountsReceivableSettings;

                    public function __construct(array $values = [])
                    {
                        $this->accountsReceivableSettings = new AccountsReceivableSettings();
                        $this->accountsReceivableSettings->payment_retry_schedule = [1, 2, 3];
                        $this->accountsReceivableSettings->autopay_delay_days = 0;
                        parent::__construct($values);
                    }

                    public function getAccountsReceivableSettingsValue(): AccountsReceivableSettings
                    {
                        return $this->accountsReceivableSettings;
                    }
                };
                $this->_company = $company;
                // mock the customer and perset default values
                $this->_customer = new Customer(['id' => -1]);
                $this->_customer->autopay_delay_days = $values['autopay_delay_days'] ?? -1;
                parent::__construct($values);
            }

            public function tenant(): Company
            {
                return $this->_company;
            }

            public function customer(): Customer
            {
                return $this->_customer;
            }
        };

        return $invoice;
    }
}
