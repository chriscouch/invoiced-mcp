<?php

namespace App\Core\Search\Libs;

use App\AccountsPayable\Models\Vendor;
use App\AccountsPayable\Search\VendorSearchDocument;
use App\AccountsReceivable\Models\Contact;
use App\AccountsReceivable\Models\CreditNote;
use App\AccountsReceivable\Models\Customer;
use App\AccountsReceivable\Models\Estimate;
use App\AccountsReceivable\Models\Invoice;
use App\AccountsReceivable\Search\ContactSearchDocument;
use App\AccountsReceivable\Search\CreditNoteSearchDocument;
use App\AccountsReceivable\Search\CustomerSearchDocument;
use App\AccountsReceivable\Search\EstimateSearchDocument;
use App\AccountsReceivable\Search\InvoiceSearchDocument;
use App\CashApplication\Models\Payment;
use App\CashApplication\Search\PaymentSearchDocument;
use App\Core\Search\Interfaces\SearchDocumentInterface;
use App\Sending\Email\Models\EmailParticipant;
use App\Sending\Email\Search\EmailParticipantSearchDocument;
use App\SubscriptionBilling\Models\Subscription;
use App\SubscriptionBilling\Search\SubscriptionSearchDocument;
use Exception;

class SearchDocumentFactory
{
    public function make(object $model): array
    {
        return $this->getDocument($model)->toSearchDocument();
    }

    private function getDocument(object $model): SearchDocumentInterface
    {
        return match ($model::class) {
            Contact::class => new ContactSearchDocument($model),
            CreditNote::class => new CreditNoteSearchDocument($model),
            Customer::class => new CustomerSearchDocument($model),
            EmailParticipant::class => new EmailParticipantSearchDocument($model),
            Estimate::class => new EstimateSearchDocument($model),
            Invoice::class => new InvoiceSearchDocument($model),
            Payment::class => new PaymentSearchDocument($model),
            Subscription::class => new SubscriptionSearchDocument($model),
            Vendor::class => new VendorSearchDocument($model),
            default => throw new Exception('Model not supported for indexing: '.$model::class),
        };
    }
}
