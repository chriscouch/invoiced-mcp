<?php

namespace App\Companies\Libs;

use App\Companies\Models\Company;
use App\Companies\Models\CompanyPhoneNumber;
use App\Companies\Models\CompanyTaxId;
use App\Companies\Models\Member;
use App\Core\Authentication\Libs\LoginHelper;
use App\Core\Authentication\Models\AccountSecurityEvent;
use App\Core\Authentication\Models\User;
use App\Core\Billing\BillingSystem\BillingSystemFactory;
use App\Core\Billing\Exception\BillingException;
use App\Core\Billing\Models\BillingProfile;
use App\Core\Statsd\Interfaces\StatsdAwareInterface;
use App\Core\Statsd\StatsdAwareTrait;
use App\Core\Utils\IpUtilities;
use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Output\OutputInterface;

class MarkCompanyFraudulent implements StatsdAwareInterface
{
    use StatsdAwareTrait;

    public function __construct(
        private Connection $database,
        private BillingSystemFactory $billingSystemFactory,
        private LoginHelper $loginHelper
    ) {
    }

    public function markFraud(Company $company, OutputInterface $output = null): void
    {
        $this->cancelCompany($company, $output);

        // disable all associated user accounts
        $creator = $company->creator();
        if ($creator) {
            $this->disableUser($creator, $company, $output);
        }

        $members = Member::queryWithTenant($company)
            ->where('expires', 0)
            ->all();
        foreach ($members as $member) {
            $user = $member->user();
            if ($user->id() != $creator?->id()) {
                $this->disableUser($user, $company, $output);
            }
        }

        $this->statsd->increment('security.fraudulent_account');
    }

    private function cancelCompany(Company $company, OutputInterface $output = null): void
    {
        $this->database->update('Companies', [
            'fraud' => 1,
            'canceled' => 1,
            'canceled_at' => time(),
            'canceled_reason' => 'fraud',
        ], ['id' => $company->id()]);

        // cancel their subscription in the billing system
        $billingProfile = BillingProfile::getOrCreate($company);
        $billingSystem = $this->billingSystemFactory->getForBillingProfile($billingProfile);
        try {
            $billingSystem->cancel($billingProfile, false);
        } catch (BillingException $e) {
            // if there is an exception when canceling the subscription
            // then it is intentionally ignored
            if ($output) {
                $output->writeln('Unable to cancel subscription: '.$e->getMessage());
            }
        }

        $company->features->remove('needs_fraud_review');

        // update the model locally so downstream event listeners have the latest values
        $company->fraud = true;

        // block all associated phone numbers
        foreach (CompanyPhoneNumber::queryWithTenant($company)->all() as $companyPhoneNumber) {
            $this->database->executeStatement('INSERT IGNORE INTO BlockListPhoneNumbers (phone) VALUES (?)', [$companyPhoneNumber->phone]);
            $output?->writeln('Add phone # '.$companyPhoneNumber->phone.' to block list');
        }

        // block all associated tax ids
        foreach (CompanyTaxId::queryWithTenant($company)->all() as $companyTaxId) {
            $hash = md5($companyTaxId->country.'_'.$companyTaxId->tax_id);
            $this->database->executeStatement('INSERT IGNORE INTO BlockListTaxIds (tax_id_hash) VALUES (?)', [$hash]);
            $output?->writeln('Add tax ID to block list');
        }

        if ($output) {
            $output->writeln("Canceled company # {$company->id()}");
        }
    }

    private function disableUser(User $user, Company $company, OutputInterface $output = null): void
    {
        $this->database->update('Users', ['enabled' => 0], ['id' => $user->id()]);

        if ($output) {
            $output->writeln('Disabled user account # '.$user->id());
        }

        // sign them out of all sessions
        $this->loginHelper->signOutAllSessions($user);

        if ($output) {
            $output->writeln('Signed user out of all active sessions');
        }

        // delete all active API keys
        $this->database->delete('ApiKeys', ['user_id' => $user->id()]);
        $this->database->delete('ApiKeys', ['tenant_id' => $company->id()]);

        if ($output) {
            $output->writeln('Deleted all active API keys');
        }

        // block associated IP addresses
        if ($ip = $user->ip) {
            IpUtilities::blockIp($ip, $this->database);
            $output?->writeln('Added IP address '.$ip.' to block list');
        }

        foreach (AccountSecurityEvent::where('user_id', $user)->all() as $securityEvent) {
            if ($ip = $securityEvent->ip) {
                IpUtilities::blockIp($ip, $this->database);
                $output?->writeln('Added IP address '.$ip.' to block list');
            }
        }
    }
}
