<?php

namespace App\Tests;

use App\AccountsPayable\Enums\CheckStock;
use App\AccountsPayable\Enums\PayableDocumentStatus;
use App\AccountsPayable\Models\Bill;
use App\AccountsPayable\Models\CompanyBankAccount;
use App\AccountsPayable\Models\CompanyCard;
use App\AccountsPayable\Models\Vendor;
use App\AccountsPayable\Models\VendorCredit;
use App\AccountsPayable\Models\VendorPaymentBatch;
use App\AccountsPayable\Models\VendorPaymentBatchBill;
use App\AccountsReceivable\Models\Coupon;
use App\AccountsReceivable\Models\CreditNote;
use App\AccountsReceivable\Models\Customer;
use App\AccountsReceivable\Models\Estimate;
use App\AccountsReceivable\Models\Invoice;
use App\AccountsReceivable\Models\Item;
use App\CashApplication\Models\Payment;
use App\CashApplication\Models\Transaction;
use App\Chasing\Models\LateFeeSchedule;
use App\Companies\Models\Company;
use App\Core\Entitlements\Models\Product;
use App\Core\Files\Models\File;
use App\Core\Utils\InfuseUtility as Utility;
use App\Core\Utils\ValueObjects\Interval;
use App\Integrations\AccountingSync\Models\AccountingSyncProfile;
use App\Integrations\Avalara\AvalaraAccount;
use App\Integrations\Enums\IntegrationType;
use App\Integrations\Intacct\Models\IntacctAccount;
use App\Integrations\NetSuite\Models\NetSuiteAccount;
use App\Integrations\OAuth\Models\OAuthAccount;
use App\Integrations\QuickBooksOnline\Models\QuickBooksAccount;
use App\Integrations\Slack\SlackAccount;
use App\Integrations\Twilio\TwilioAccount;
use App\Integrations\Xero\Models\XeroAccount;
use App\Metadata\Models\CustomField;
use App\Network\Enums\DocumentStatus;
use App\Network\Enums\NetworkDocumentType;
use App\Network\Models\NetworkConnection;
use App\Network\Models\NetworkDocument;
use App\Network\Models\NetworkDocumentStatusTransition;
use App\Network\Models\NetworkInvitation;
use App\PaymentProcessing\Models\BankAccount;
use App\PaymentProcessing\Models\Card;
use App\PaymentProcessing\Models\MerchantAccount;
use App\PaymentProcessing\Models\PaymentMethod;
use App\SalesTax\Models\TaxRate;
use App\Sending\Email\Models\EmailThread;
use App\Sending\Email\Models\Inbox;
use App\Sending\Email\Models\InboxEmail;
use App\Sending\Email\Models\SmtpAccount;
use App\SubscriptionBilling\Models\Plan;
use App\SubscriptionBilling\Models\Subscription;
use Carbon\CarbonImmutable;
use Symfony\Component\DependencyInjection\ContainerInterface;

class TestDataFactory
{
    public function __construct(
        private ContainerInterface $container,
    ) {
    }

    public function createCompany(): Company
    {
        $this->getService('App\Metadata\Libs\LegacyStorageFacade')::$instance = null;
        $this->getService('App\Metadata\Libs\AttributeStorageFacade')::$instance = null;
        $this->getService('test.tenant')->clear();

        $company = new Company();
        $company->name = 'TEST';
        $company->username = 'test'.time().rand();
        $company->address1 = 'Company';
        $company->address2 = 'Address';
        $company->city = 'Austin';
        $company->state = 'TX';
        $company->postal_code = '78701';
        $company->country = 'US';
        $company->email = 'test@example.com';
        $company->creator_id = $this->getService('test.user_context')->get()->id();
        $company->saveOrFail();

        // enable all products
        $installer = self::getService('test.product_installer');
        foreach (Product::first(100) as $product) {
            if (!str_contains($product, 'Trial') && !str_contains($product, 'Free') && !str_contains($product, 'Sandbox')) {
                $installer->install($product, $company);
            }
        }

        $this->getService('test.tenant')->set($company);

        return $company;
    }

    public function createCustomer(): Customer
    {
        $customer = new Customer();
        $customer->name = 'Sherlock';
        $customer->email = 'sherlock@example.com';
        $customer->address1 = 'Test';
        $customer->address2 = 'Address';
        $customer->city = 'Austin';
        $customer->state = 'TX';
        $customer->postal_code = '78701';
        $customer->country = 'US';
        $customer->saveOrFail();

        return $customer;
    }

    public function createInactiveCustomer(): Customer
    {
        $customer = new Customer();
        $customer->name = 'Sherlock';
        $customer->email = 'sherlock@example.com';
        $customer->address1 = 'Test';
        $customer->address2 = 'Address';
        $customer->city = 'Austin';
        $customer->state = 'TX';
        $customer->postal_code = '78701';
        $customer->country = 'US';
        $customer->active = false;
        $customer->saveOrFail();

        return $customer;
    }

    public function createInbox(): Inbox
    {
        $inbox = new Inbox();
        $inbox->external_id = 'abcdefhij';
        $inbox->saveOrFail();

        return $inbox;
    }

    public function createEmailThread(Company $company, Inbox $inbox): EmailThread
    {
        $thread = new EmailThread();
        $thread->tenant_id = $company->id;
        $thread->status = 'open';
        $thread->inbox = $inbox;
        $thread->name = 'test';
        $thread->saveOrFail();

        return $thread;
    }

    public function createInboxEmail(EmailThread $thread, ?string $trackingId = null): InboxEmail
    {
        $inboxEmail = new InboxEmail();
        $inboxEmail->thread = $thread;
        $inboxEmail->incoming = false;
        $inboxEmail->tracking_id = $trackingId;
        $inboxEmail->message_id = '<'.uniqid().'@invoiced.com>';
        $inboxEmail->saveOrFail();

        return $inboxEmail;
    }

    public function createEstimate(Customer $customer): Estimate
    {
        $estimate = new Estimate();
        $estimate->setCustomer($customer);
        $estimate->items = [
            [
                'name' => 'Test Item',
                'description' => 'test',
                'quantity' => 1,
                'unit_cost' => 100,
            ],
        ];
        $estimate->saveOrFail();

        return $estimate;
    }

    public function createInvoice(Customer $customer): Invoice
    {
        $invoice = new Invoice();
        $invoice->setCustomer($customer);
        $invoice->items = [
            [
                'name' => 'Test Item',
                'description' => 'test',
                'quantity' => 1,
                'unit_cost' => 100,
            ],
        ];
        $invoice->saveOrFail();

        return $invoice;
    }

    public function createCreditNote(Customer $customer): CreditNote
    {
        $creditNote = new CreditNote();
        $creditNote->setCustomer($customer);
        $creditNote->items = [
            [
                'name' => 'Test Item',
                'description' => 'test',
                'quantity' => 1,
                'unit_cost' => 100,
            ],
        ];
        $creditNote->saveOrFail();

        return $creditNote;
    }

    public function createCreditNoteTransaction(CreditNote $creditNote, Invoice $invoice): Transaction
    {
        $creditNoteTransaction = new Transaction();
        $creditNoteTransaction->amount = -$creditNote->total;
        $creditNoteTransaction->currency = $creditNote->currency;
        $creditNoteTransaction->setCustomer($creditNote->customer());
        $creditNoteTransaction->setInvoice($invoice);
        $creditNoteTransaction->setCreditNote($creditNote);
        $creditNoteTransaction->type = Transaction::TYPE_ADJUSTMENT;
        $creditNoteTransaction->saveOrFail();

        return $creditNoteTransaction;
    }

    public function createItem(): Item
    {
        $item = new Item();
        $item->name = 'Test Item';
        $item->id = 'test-item';
        $item->description = 'Description';
        $item->unit_cost = 1000;
        $item->saveOrFail();

        return $item;
    }

    public function createTaxRate(): TaxRate
    {
        $taxRate = new TaxRate();
        $taxRate->id = 'tax';
        $taxRate->name = 'Tax';
        $taxRate->value = 5;
        $taxRate->saveOrFail();

        return $taxRate;
    }

    public function createCoupon(): Coupon
    {
        $coupon = new Coupon();
        $coupon->id = 'coupon';
        $coupon->name = 'Coupon';
        $coupon->value = 5;
        $coupon->saveOrFail();

        return $coupon;
    }

    public function createFile(): File
    {
        $file = new File();
        $file->name = 'something.pdf';
        $file->size = 100000;
        $file->type = 'application/pdf';
        $file->url = 'https://invoiced.com/somewhere.pdf';
        $file->saveOrFail();

        return $file;
    }

    public function createTransaction(Invoice $invoice, string $type = Transaction::TYPE_PAYMENT): Transaction
    {
        $transaction = new Transaction();
        $transaction->type = $type;
        $transaction->setInvoice($invoice);
        $transaction->setCustomer($invoice->customer());
        $transaction->amount = $invoice->balance;
        $transaction->saveOrFail();

        return $transaction;
    }

    public function createPayment(?Customer $customer = null): Payment
    {
        $payment = new Payment();
        if ($customer) {
            $payment->setCustomer($customer);
        }
        $payment->amount = 200;
        $payment->currency = 'usd';
        $payment->saveOrFail();

        return $payment;
    }

    public function createRefund(Customer $customer, Invoice $invoice, Transaction $transaction): Transaction
    {
        $refund = new Transaction();
        $refund->type = Transaction::TYPE_REFUND;
        $refund->setCustomer($customer);
        $refund->setInvoice($invoice);
        $refund->setParentTransaction($transaction);
        $refund->amount = $transaction->amount;
        $refund->saveOrFail();

        return $refund;
    }

    public function createCredit(Customer $customer): Transaction
    {
        $credit = new Transaction();
        $credit->type = Transaction::TYPE_ADJUSTMENT;
        $credit->setCustomer($customer);
        $credit->amount = -100;
        $credit->saveOrFail();

        return $credit;
    }

    public function createPlan(): Plan
    {
        $plan = new Plan();
        $plan->id = 'starter';
        $plan->name = 'Starter';
        $plan->amount = 100;
        $plan->interval = Interval::MONTH;
        $plan->interval_count = 2;
        $plan->saveOrFail();

        return $plan;
    }

    public function createSubscription(Customer $customer, Plan $plan): Subscription
    {
        return $this->getService('test.create_subscription')
            ->create([
                'customer' => $customer,
                'plan' => $plan,
                'start_date' => (int) mktime(0, 0, 0, (int) date('m'), (int) date('d'), (int) date('Y')),
            ]);
    }

    public function createMerchantAccount(string $gateway, string $gatewayId = 'TEST_MERCHANT_ID', array $credentials = ['secret' => 'TEST_API_KEY']): MerchantAccount
    {
        $this->getService('test.database')->delete('MerchantAccounts', [
            'gateway' => $gateway,
            'gateway_id' => $gatewayId,
        ]);

        $merchantAccount = new MerchantAccount();
        $merchantAccount->gateway = $gateway;
        $merchantAccount->gateway_id = $gatewayId;
        $merchantAccount->name = 'Test Merchant Account';
        $merchantAccount->top_up_threshold_num_of_days = 14;
        $merchantAccount->credentials = (object) $credentials;
        $merchantAccount->saveOrFail();

        return $merchantAccount;
    }

    public function acceptsPaymentMethod(Company $company, string $method, ?string $gateway = null, ?string $meta = null): PaymentMethod
    {
        $paymentMethod = PaymentMethod::instance($company, $method);
        $paymentMethod->enabled = true;
        $paymentMethod->meta = $meta;
        if ($gateway) {
            $paymentMethod->gateway = $gateway;
        }
        $paymentMethod->saveOrFail();

        return $paymentMethod;
    }

    public function createCard(Customer $customer, string $gateway = 'invoiced', string $gatewayID = 'card_test', int $merchantAccountID = 0): Card
    {
        $card = new Card();
        $card->customer = $customer;
        $card->brand = 'Mastercard';
        $card->funding = 'debit';
        $card->last4 = '4242';
        $card->exp_month = 1;
        $card->exp_year = 1991;
        $card->gateway = $gateway;
        $card->gateway_id = $gatewayID;
        $card->merchant_account_id = $merchantAccountID;
        $card->chargeable = true;
        $card->saveOrFail();

        return $card;
    }

    public function createBankAccount(Customer $customer, string $gateway = 'invoiced', string $gatewayID = 'bank_account_test', int $merchantAccountID = 0): BankAccount
    {
        $bankAccount = new BankAccount();
        $bankAccount->customer = $customer;
        $bankAccount->bank_name = 'Chase';
        $bankAccount->last4 = '6789';
        $bankAccount->routing_number = '012345678';
        $bankAccount->verified = false;
        $bankAccount->currency = 'usd';
        $bankAccount->gateway = $gateway;
        $bankAccount->gateway_id = $gatewayID;
        $bankAccount->merchant_account_id = $merchantAccountID;
        $bankAccount->chargeable = true;
        $bankAccount->saveOrFail();

        return $bankAccount;
    }

    public function createQuickBooksAccount(): QuickBooksAccount
    {
        $this->getService('test.database')->delete('QuickBooksAccounts', ['realm_id', '1234']);

        $quickbooksAccount = new QuickBooksAccount();
        $quickbooksAccount->realm_id = '1234';
        $quickbooksAccount->access_token = 'ACCESS_TOKEN';
        $quickbooksAccount->refresh_token = 'REFRESH_TOKEN';
        $quickbooksAccount->expires = strtotime('+60 minutes');
        $quickbooksAccount->refresh_token_expires = strtotime('+100 days');
        $quickbooksAccount->name = 'Test QuickBooks Account';
        $quickbooksAccount->saveOrFail();

        return $quickbooksAccount;
    }

    public function createOAuthAccount(IntegrationType $integration): OAuthAccount
    {
        $account = new OAuthAccount();
        $account->integration = $integration;
        $account->access_token = 'ACCESS_TOKEN';
        $account->refresh_token = 'REFRESH_TOKEN';
        $account->access_token_expiration = CarbonImmutable::now()->addHour();
        $account->refresh_token_expiration = CarbonImmutable::now()->addDays(100);
        $account->name = 'Test OAuth Account';
        $account->saveOrFail();

        return $account;
    }

    public function createAccountingSyncProfile(IntegrationType $integration): AccountingSyncProfile
    {
        $syncProfile = new AccountingSyncProfile();
        $syncProfile->integration = $integration;
        $syncProfile->saveOrFail();

        return $syncProfile;
    }

    public function createXeroAccount(): XeroAccount
    {
        $this->getService('test.database')->delete('XeroAccounts', ['organization_id' => '5678']);

        $xeroAccount = new XeroAccount();
        $xeroAccount->organization_id = '5678';
        $xeroAccount->access_token = 'ACCESS_TOKEN';
        $xeroAccount->session_handle = 'SESSION_HANDLE';
        $xeroAccount->expires = strtotime('+180 days');
        $xeroAccount->name = 'Test Xero Account';
        $xeroAccount->saveOrFail();

        return $xeroAccount;
    }

    public function createIntacctAccount(): IntacctAccount
    {
        $this->getService('test.database')->delete('IntacctAccounts', ['intacct_company_id' => '1234']);

        $intacctAccount = new IntacctAccount();
        $intacctAccount->intacct_company_id = '1234';
        $intacctAccount->user_id = 'user';
        $intacctAccount->user_password = 'user_password';
        $intacctAccount->name = 'Test Intacct Account';
        $intacctAccount->saveOrFail();

        return $intacctAccount;
    }

    public function createNetSuiteAccount(): NetSuiteAccount
    {
        $this->getService('test.database')->delete('NetSuiteAccounts', ['account_id' => '1234']);

        $netsuiteAccount = new NetSuiteAccount();
        $netsuiteAccount->account_id = '1234';
        $netsuiteAccount->token = 'token';
        $netsuiteAccount->token_secret = 'token secret';
        $netsuiteAccount->name = 'Test NetSuite Account';
        $netsuiteAccount->restlet_domain = 'https://1234.restlets.api.netsuite.com';
        $netsuiteAccount->saveOrFail();

        return $netsuiteAccount;
    }

    public function createAvalaraAccount(): AvalaraAccount
    {
        $avalaraAccount = new AvalaraAccount();
        $avalaraAccount->company_code = 'company_code';
        $avalaraAccount->name = 'Test Avalara Account';
        $avalaraAccount->license_key = 'shhh';
        $avalaraAccount->account_id = '1234';
        $avalaraAccount->commit_mode = AvalaraAccount::COMMIT_MODE_COMMITTED;
        $avalaraAccount->saveOrFail();

        return $avalaraAccount;
    }

    public function createSlackAccount(): SlackAccount
    {
        $slackAccount = new SlackAccount();
        $slackAccount->team_id = 'team_id';
        $slackAccount->name = 'Test Slack Account';
        $slackAccount->access_token = 'shhh';
        $slackAccount->webhook_url = 'http://example.com';
        $slackAccount->webhook_config_url = 'http://example.com/settings';
        $slackAccount->webhook_channel = '#general';
        $slackAccount->saveOrFail();

        return $slackAccount;
    }

    public function createTwilioAccount(): TwilioAccount
    {
        $twilioAccount = new TwilioAccount();
        $twilioAccount->account_sid = '1234';
        $twilioAccount->auth_token = 'password';
        $twilioAccount->from_number = '+1123456789';
        $twilioAccount->saveOrFail();

        return $twilioAccount;
    }

    public function createCustomField(string $object = 'customer'): CustomField
    {
        $customField = new CustomField();
        $customField->id = 'test';
        $customField->object = $object;
        $customField->name = 'Test';
        $customField->saveOrFail();

        return $customField;
    }

    public function createSmtpAccount(): SmtpAccount
    {
        $smtpAccount = new SmtpAccount();
        $smtpAccount->host = 'host';
        $smtpAccount->username = 'username';
        $smtpAccount->password = 'password';
        $smtpAccount->port = 1234;
        $smtpAccount->encryption = 'tls';
        $smtpAccount->auth_mode = 'login';
        $smtpAccount->saveOrFail();

        return $smtpAccount;
    }

    public function createLateFeeSchedule(): LateFeeSchedule
    {
        $lateFeeSchedule = new LateFeeSchedule();
        $lateFeeSchedule->name = 'My Late Fee Schedule';
        $lateFeeSchedule->start_date = CarbonImmutable::now();
        $lateFeeSchedule->amount = 5;
        $lateFeeSchedule->is_percent = true;
        $lateFeeSchedule->grace_period = 30;
        $lateFeeSchedule->saveOrFail();

        return $lateFeeSchedule;
    }

    public function createNetworkInvitation(Company $from, Company $to): NetworkInvitation
    {
        $invitation = new NetworkInvitation();
        $invitation->uuid = Utility::guid();
        $invitation->from_company = $from;
        $invitation->to_company = $to;
        $invitation->is_customer = true;
        $invitation->saveOrFail();

        return $invitation;
    }

    public function connectCompanies(Company $vendor, Company $customer): NetworkConnection
    {
        $connection = new NetworkConnection();
        $connection->vendor = $vendor;
        $connection->customer = $customer;
        $connection->saveOrFail();

        return $connection;
    }

    public function createNetworkDocument(Company $from, Company $to): NetworkDocument
    {
        $document = new NetworkDocument();
        $document->from_company = $from;
        $document->to_company = $to;
        $document->type = NetworkDocumentType::Invoice;
        $document->reference = 'INV-'.uniqid();
        $document->currency = 'usd';
        $document->total = 1000;
        $document->current_status = DocumentStatus::PendingApproval;
        $document->saveOrFail();

        return $document;
    }

    public function createNetworkDocumentStatusTransition(Company $from, NetworkDocument $document, DocumentStatus $status): NetworkDocumentStatusTransition
    {
        $transition = new NetworkDocumentStatusTransition();
        $transition->document = $document;
        $transition->company = $from;
        $transition->effective_date = CarbonImmutable::now();
        $transition->status = $status;
        $transition->saveOrFail();

        return $transition;
    }

    public function createVendor(): Vendor
    {
        $vendor = new Vendor();
        $vendor->name = 'Test Vendor';
        $vendor->saveOrFail();

        return $vendor;
    }

    public function createBill(Vendor $vendor): Bill
    {
        $bill = new Bill();
        $bill->vendor = $vendor;
        $bill->number = 'INV-'.uniqid();
        $bill->date = CarbonImmutable::now();
        $bill->currency = 'usd';
        $bill->total = 1000;
        $bill->status = PayableDocumentStatus::PendingApproval;
        $bill->saveOrFail();

        return $bill;
    }

    public function createVendorCredit(Vendor $vendor): VendorCredit
    {
        $bill = new VendorCredit();
        $bill->vendor = $vendor;
        $bill->number = 'INV-'.uniqid();
        $bill->date = CarbonImmutable::now();
        $bill->currency = 'usd';
        $bill->total = 1000;
        $bill->status = PayableDocumentStatus::PendingApproval;
        $bill->saveOrFail();

        return $bill;
    }

    public function createCompanyBankAccount(): CompanyBankAccount
    {
        $bankAccount = new CompanyBankAccount();
        $bankAccount->name = 'test';
        $bankAccount->check_number = 1;
        $bankAccount->saveOrFail();

        return $bankAccount;
    }

    public function createBatchPayment(?CompanyBankAccount $bankAccount = null, string $paymentMethod = 'print_check', ?CompanyCard $card = null): VendorPaymentBatch
    {
        $batchPayment = new VendorPaymentBatch();
        $batchPayment->currency = 'usd';
        $batchPayment->payment_method = $paymentMethod;
        $batchPayment->bank_account = $bankAccount;
        $batchPayment->card = $card;
        $batchPayment->check_layout = CheckStock::CheckOnTop;
        $batchPayment->initial_check_number = 1;
        $batchPayment->saveOrFail();

        return $batchPayment;
    }

    public function createBatchPaymentBill(Vendor $vendor, VendorPaymentBatch $batchPayment, Bill $bill): VendorPaymentBatchBill
    {
        $batchPaymentBill = new VendorPaymentBatchBill();
        $batchPaymentBill->vendor_payment_batch = $batchPayment;
        $batchPaymentBill->bill_number = $bill->number;
        $batchPaymentBill->vendor = $vendor;
        $batchPaymentBill->amount = 100;
        $batchPaymentBill->bill = $bill;
        $batchPaymentBill->saveOrFail();

        return $batchPaymentBill;
    }

    private function getService(string $id): mixed
    {
        return $this->container->get($id);
    }
}
