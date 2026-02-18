<?php

namespace App\EntryPoint\CronJob;

use App\AccountsReceivable\Models\Customer;
use App\Core\Multitenant\TenantContext;
use App\Core\Orm\Query;
use App\Core\Utils\Enums\ObjectType;
use App\PaymentProcessing\Gateways\StripeGateway;
use App\PaymentProcessing\Models\BankAccount;
use App\Sending\Email\EmailFactory\CustomerEmailFactory;
use App\Sending\Email\Exceptions\SendEmailException;
use App\Sending\Email\Libs\EmailSender;

/**
 * Sends a reminder to all customers with connected,
 * unverified bank accounts that need it.
 */
class SendAchVerificationReminders extends AbstractTaskQueueCronJob
{
    private const BATCH_SIZE = 1000;

    private int $count;

    public function __construct(private TenantContext $tenant, private EmailSender $emailSender, private CustomerEmailFactory $emailFactory)
    {
    }

    public static function getLockTtl(): int
    {
        return 1800;
    }

    public function getTasks(): iterable
    {
        $query = $this->getBankAccounts();
        $this->count = $query->count();

        return $query->first(self::BATCH_SIZE);
    }

    public function getTaskCount(): int
    {
        return $this->count;
    }

    /**
     * @param BankAccount $task
     */
    public function runTask(mixed $task): bool
    {
        $company = $task->tenant();

        // check if the company is in good standing
        if (!$company->billingStatus()->isActive()) {
            return false;
        }

        // IMPORTANT: set the current tenant to enable multitenant operations
        $this->tenant->set($company);

        $sent = $this->sendVerificationRequest($task);

        // IMPORTANT: clear the current tenant after we are done
        $this->tenant->clear();

        return $sent;
    }

    /**
     * Gets bank accounts that need a reminder sent.
     */
    public function getBankAccounts(): Query
    {
        $threeDaysAgo = strtotime('-3 days');

        return BankAccount::queryWithoutMultitenancyUnsafe()
            ->join(Customer::class, 'customer_id', 'Customers.id')
            ->where('verified', false)
            ->where('verification_last_sent', $threeDaysAgo, '<=')
            ->where('gateway IN ("'.StripeGateway::ID.'")')
            ->where(
                'Customers.default_source_type',
                ObjectType::BankAccount->typeName()
            )
            ->where('Customers.default_source_id = BankAccounts.id');
    }

    /**
     * Sends a verification request email when an account needs
     * microdeposit verification.
     */
    public function sendVerificationRequest(BankAccount $task): bool
    {
        $customer = $task->customer;

        $templateVars = [
            'verifyUrl' => $task->tenant()->url.'/paymentInfo/'.$customer->client_id.'/ach/verify/'.$task->id(),
            'last4' => $task->last4,
        ];

        try {
            $email = $this->emailFactory->make($customer, 'bank-verification-request', $templateVars, $customer->emailContacts(), 'Please verify your bank account');
            $this->emailSender->send($email);
        } catch (SendEmailException) {
            return false;
        }

        $task->verification_last_sent = time();
        $task->saveOrFail();

        return true;
    }
}
