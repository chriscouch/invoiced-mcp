<?php

namespace App\Tests;

use App\AccountsPayable\Models\ApprovalWorkflow;
use App\AccountsPayable\Models\ApprovalWorkflowPath;
use App\AccountsPayable\Models\ApprovalWorkflowStep;
use App\AccountsPayable\Models\Bill;
use App\AccountsPayable\Models\CompanyBankAccount;
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
use App\Companies\Models\Member;
use App\Core\Authentication\Models\User;
use App\Core\Files\Models\File;
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
use App\PaymentProcessing\Enums\PaymentMethodType;
use App\PaymentProcessing\Gateways\FlywireGateway;
use App\PaymentProcessing\Gateways\GoCardlessGateway;
use App\PaymentProcessing\Gateways\MockGateway;
use App\PaymentProcessing\Libs\PaymentGatewayMetadata;
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
use Exception;

trait TestDataCaseTrait
{
    protected static AccountingSyncProfile $accountingSyncProfile;
    protected static AvalaraAccount $avalaraAccount;
    protected static BankAccount $bankAccount;
    protected static Bill $bill;
    protected static Card $card;
    protected static Company $company;
    protected static CompanyBankAccount $companyBankAccount;
    protected static Coupon $coupon;
    protected static CreditNote $creditNote;
    protected static CustomField $customField;
    protected static Customer $customer;
    protected static Customer $inactiveCustomer;
    protected static EmailThread $thread;
    protected static Estimate $estimate;
    protected static File $file;
    protected static Inbox $inbox;
    protected static InboxEmail $inboxEmail;
    protected static IntacctAccount $intacctAccount;
    protected static Invoice $invoice;
    protected static Item $item;
    protected static LateFeeSchedule $lateFeeSchedule;
    protected static MerchantAccount $merchantAccount;
    protected static NetSuiteAccount $netsuiteAccount;
    protected static OAuthAccount $oAuthAccount;
    protected static Payment $payment;
    protected static Plan $plan;
    protected static QuickBooksAccount $quickbooksAccount;
    protected static SlackAccount $slackAccount;
    protected static SmtpAccount $smtpAccount;
    protected static Subscription $subscription;
    protected static TaxRate $taxRate;
    protected static Transaction $credit;
    protected static Transaction $creditNoteTransaction;
    protected static Transaction $refund;
    protected static Transaction $transaction;
    protected static TwilioAccount $twilioAccount;
    protected static Vendor $vendor;
    protected static VendorCredit $vendorCredit;
    protected static VendorPaymentBatch $batchPayment;
    protected static VendorPaymentBatchBill $batchPaymentBill;
    protected static XeroAccount $xeroAccount;

    protected static function hasCompany(): void
    {
        if (isset(self::$company) && self::$company->persisted()) {
            throw new Exception('Cannot create a new company until the previous one is deleted');
        }

        self::$company = self::getTestDataFactory()->createCompany();
    }

    protected static function hasCustomer(): void
    {
        self::$customer = self::getTestDataFactory()->createCustomer();
    }

    protected static function hasInactiveCustomer(): void
    {
        self::$inactiveCustomer = self::getTestDataFactory()->createInactiveCustomer();
    }

    protected static function hasInbox(): void
    {
        self::$inbox = self::getTestDataFactory()->createInbox();
    }

    protected static function hasEmailThread(): void
    {
        self::$thread = self::getTestDataFactory()->createEmailThread(self::$company, self::$inbox);
    }

    protected static function hasInboxEmail(?string $trackingId = null): void
    {
        self::$inboxEmail = self::getTestDataFactory()->createInboxEmail(self::$thread, $trackingId);
    }

    protected static function hasEstimate(): void
    {
        self::$estimate = self::getTestDataFactory()->createEstimate(self::$customer);
    }

    protected static function hasInvoice(): void
    {
        self::$invoice = self::getTestDataFactory()->createInvoice(self::$customer);
    }

    protected static function hasUnappliedCreditNote(): void
    {
        self::$creditNote = self::getTestDataFactory()->createCreditNote(self::$customer);
    }

    protected static function hasCreditNote(): void
    {
        self::$creditNote = self::getTestDataFactory()->createCreditNote(self::$customer);
        self::$creditNoteTransaction = self::getTestDataFactory()->createCreditNoteTransaction(self::$creditNote, self::$invoice);
    }

    protected static function hasItem(): void
    {
        self::$item = self::getTestDataFactory()->createItem();
    }

    protected static function hasTaxRate(): void
    {
        self::$taxRate = self::getTestDataFactory()->createTaxRate();
    }

    protected static function hasCoupon(): void
    {
        self::$coupon = self::getTestDataFactory()->createCoupon();
    }

    protected static function hasFile(): void
    {
        self::$file = self::getTestDataFactory()->createFile();
    }

    protected static function hasTransaction(string $type = Transaction::TYPE_PAYMENT): void
    {
        self::$transaction = self::getTestDataFactory()->createTransaction(self::$invoice, $type);
    }

    protected static function hasPayment(?Customer $customer = null): void
    {
        self::$payment = self::getTestDataFactory()->createPayment($customer);
    }

    protected static function hasRefund(): void
    {
        self::$refund = self::getTestDataFactory()->createRefund(self::$customer, self::$invoice, self::$transaction);
    }

    protected static function hasCredit(): void
    {
        self::$credit = self::getTestDataFactory()->createCredit(self::$customer);
    }

    protected static function hasPlan(): void
    {
        self::$plan = self::getTestDataFactory()->createPlan();
    }

    protected static function hasSubscription(): void
    {
        self::$subscription = self::getTestDataFactory()->createSubscription(self::$customer, self::$plan);
    }

    protected static function hasMerchantAccount(string $gateway, string $gatewayId = 'TEST_MERCHANT_ID', array $credentials = ['secret' => 'TEST_API_KEY']): void
    {
        self::$merchantAccount = self::getTestDataFactory()->createMerchantAccount($gateway, $gatewayId, $credentials);
    }

    protected static function acceptsChecks(): void
    {
        self::acceptsPaymentMethod(PaymentMethodType::Check->toString(), null, 'Payment instructions...');
    }

    protected static function acceptsCreditCards(string $gateway = MockGateway::ID): void
    {
        self::acceptsPaymentMethod(PaymentMethodType::Card->toString(), $gateway);
    }

    protected static function acceptsACH(string $gateway = MockGateway::ID): void
    {
        self::acceptsPaymentMethod(PaymentMethodType::Ach->toString(), $gateway);
    }

    protected static function acceptsPayPal(): void
    {
        self::acceptsPaymentMethod(PaymentMethodType::PayPal->toString(), PaymentGatewayMetadata::PAYPAL, 'test@example.com');
    }

    protected static function acceptsDirectDebit(string $gateway = GoCardlessGateway::ID): void
    {
        self::acceptsPaymentMethod(PaymentMethodType::DirectDebit->toString(), $gateway);
    }

    protected static function acceptsFlywire(): void
    {
        self::hasMerchantAccount(FlywireGateway::ID, 'ABC');

        $paymentMethod = PaymentMethod::instance(self::$company, PaymentMethodType::Card->toString());
        $paymentMethod->enabled = true;
        $paymentMethod->gateway = FlywireGateway::ID;
        $paymentMethod->setMerchantAccount(self::$merchantAccount);
        $paymentMethod->saveOrFail();
    }

    protected static function acceptsPaymentMethod(string $method, ?string $gateway = null, ?string $meta = null): void
    {
        self::getTestDataFactory()->acceptsPaymentMethod(self::$company, $method, $gateway, $meta);
    }

    protected static function hasCard(string $gateway = 'stripe', string $gatewayID = 'card_test'): void
    {
        $merchantAccountID = isset(self::$merchantAccount) ? self::$merchantAccount->id : 0;
        self::$card = self::getTestDataFactory()->createCard(self::$customer, $gateway, $gatewayID, $merchantAccountID);
    }

    protected static function hasBankAccount(string $gateway = 'stripe', string $gatewayID = 'bank_account_test'): void
    {
        $merchantAccountID = isset(self::$merchantAccount) ? self::$merchantAccount->id : 0;
        self::$bankAccount = self::getTestDataFactory()->createBankAccount(self::$customer, $gateway, $gatewayID, $merchantAccountID);
    }

    protected static function hasQuickBooksAccount(): void
    {
        self::$quickbooksAccount = self::getTestDataFactory()->createQuickBooksAccount();
    }

    protected static function hasOAuthAccount(IntegrationType $integration): void
    {
        self::$oAuthAccount = self::getTestDataFactory()->createOAuthAccount($integration);
    }

    protected static function hasAccountingSyncProfile(IntegrationType $integration): void
    {
        self::$accountingSyncProfile = self::getTestDataFactory()->createAccountingSyncProfile($integration);
    }

    protected static function hasXeroAccount(): void
    {
        self::$xeroAccount = self::getTestDataFactory()->createXeroAccount();
    }

    protected static function hasIntacctAccount(): void
    {
        self::$intacctAccount = self::getTestDataFactory()->createIntacctAccount();
    }

    protected static function hasNetSuiteAccount(): void
    {
        self::$netsuiteAccount = self::getTestDataFactory()->createNetSuiteAccount();
    }

    protected static function hasAvalaraAccount(): void
    {
        self::$avalaraAccount = self::getTestDataFactory()->createAvalaraAccount();
    }

    protected static function hasSlackAccount(): void
    {
        self::$slackAccount = self::getTestDataFactory()->createSlackAccount();
    }

    protected static function hasTwilioAccount(): void
    {
        self::$twilioAccount = self::getTestDataFactory()->createTwilioAccount();
    }

    protected static function hasCustomField(string $object = 'customer'): void
    {
        self::$customField = self::getTestDataFactory()->createCustomField($object);
    }

    protected static function hasSmtpAccount(): void
    {
        self::$smtpAccount = self::getTestDataFactory()->createSmtpAccount();
    }

    protected static function hasLateFeeSchedule(): void
    {
        self::$lateFeeSchedule = self::getTestDataFactory()->createLateFeeSchedule();
    }

    protected static function hasVendor(): void
    {
        self::$vendor = self::getTestDataFactory()->createVendor();
    }

    protected static function hasBill(): void
    {
        self::$bill = self::getTestDataFactory()->createBill(self::$vendor);
    }

    protected static function hasWorkflow(bool $enabled = false): ApprovalWorkflow
    {
        $workflow = new ApprovalWorkflow();
        $workflow->name = 'Test '.microtime(true);
        $workflow->default = false;
        $workflow->enabled = $enabled;
        $workflow->saveOrFail();

        return $workflow;
    }

    protected static function hasPath(ApprovalWorkflow $workflow, string $rules = ''): ApprovalWorkflowPath
    {
        $path = new ApprovalWorkflowPath();
        $path->approval_workflow = $workflow;
        $path->rules = $rules;
        $path->saveOrFail();

        return $path;
    }

    protected static function hasStep(ApprovalWorkflowPath $path, int $order = 1, array $members = []): ApprovalWorkflowStep
    {
        $step = new ApprovalWorkflowStep();
        $step->approval_workflow_path = $path;
        $step->order = $order;
        $step->members = $members;
        $step->saveOrFail();

        return $step;
    }

    protected static function hasMember(string $index): Member
    {
        $email = "vendor_document_$index@example.com";
        $user = User::where('email', $email)->oneOrNull();
        if (!$user) {
            $user = new User();
            $user->create([
                'first_name' => "Bob$index",
                'last_name' => "Loblaw$index",
                'email' => $email,
                'password' => ['TestPassw0rd!', 'TestPassw0rd!'],
                'ip' => '127.0.0.1',
            ]);
            $user->saveOrFail();
        }
        $member = new Member();
        $member->role = 'employee';
        $member->setUser($user);
        $member->saveOrFail();

        return $member;
    }

    protected static function hasCompanyBankAccount(): void
    {
        self::$companyBankAccount = self::getTestDataFactory()->createCompanyBankAccount();
    }

    protected static function hasBatchPayment(): void
    {
        self::$batchPayment = self::getTestDataFactory()->createBatchPayment(self::$companyBankAccount);
    }

    protected static function hasBatchPaymentBill(): void
    {
        self::$batchPaymentBill = self::getTestDataFactory()->createBatchPaymentBill(self::$vendor, self::$batchPayment, self::$bill);
    }
}
