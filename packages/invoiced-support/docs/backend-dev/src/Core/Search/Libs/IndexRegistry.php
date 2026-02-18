<?php

namespace App\Core\Search\Libs;

use App\AccountsPayable\Models\Vendor;
use App\AccountsReceivable\Models\Contact;
use App\AccountsReceivable\Models\CreditNote;
use App\AccountsReceivable\Models\Customer;
use App\AccountsReceivable\Models\Estimate;
use App\AccountsReceivable\Models\Invoice;
use App\CashApplication\Models\Payment;
use App\Companies\Models\Company;
use App\Sending\Email\Models\EmailParticipant;
use App\SubscriptionBilling\Models\Subscription;

class IndexRegistry
{
    /**
     * Gets the objects that need to be indexed for a given account.
     */
    public function getIndexableObjectsForCompany(Company $company): array
    {
        $objects = [EmailParticipant::class];

        if ($company->features->has('accounts_receivable')) {
            $objects[] = Contact::class;
            $objects[] = CreditNote::class;
            $objects[] = Customer::class;
            $objects[] = Invoice::class;
            $objects[] = Payment::class;
        }

        if ($company->features->has('subscriptions')) {
            $objects[] = Subscription::class;
        }

        if ($company->features->has('estimates')) {
            $objects[] = Estimate::class;
        }

        if ($company->features->has('accounts_payable')) {
            $objects[] = Vendor::class;
        }

        return $objects;
    }

    /**
     * Gets all objects that can be indexed.
     */
    public function getIndexableObjects(): array
    {
        return [
            EmailParticipant::class,
            Contact::class,
            CreditNote::class,
            Customer::class,
            Invoice::class,
            Payment::class,
            Subscription::class,
            Estimate::class,
            Vendor::class,
        ];
    }
}
