<?php

namespace App\AccountsReceivable\Libs;

use App\AccountsReceivable\Models\Discount;
use App\AccountsReceivable\Models\Invoice;
use App\AccountsReceivable\Models\LineItem;
use App\AccountsReceivable\Models\ReceivableDocument;
use App\Companies\Models\Company;
use App\Core\Database\TransactionManager;
use App\Core\Orm\Query;

class ExpiredDiscountCleaner
{
    public function __construct(
        private TransactionManager $transactionManager,
    ) {
    }

    /**
     * Gets all expired discounts that have not been deleted yet.
     */
    public function getExpiredDiscounts(Company $company = null): Query
    {
        if ($company) {
            $query = Discount::queryWithTenant($company);
        } else {
            $query = Discount::queryWithoutMultitenancyUnsafe();
        }

        return $query->where('expires', time(), '<')
            ->where('expires', 0, '>')
            ->where('(invoice_id IS NOT NULL OR credit_note_id IS NOT NULL OR estimate_id IS NOT NULL OR line_item_id IS NOT NULL)');
    }

    /**
     * Clears out a discount that has expired.
     */
    public function handleExpiredDiscount(Discount $discount): bool
    {
        return $this->transactionManager->perform(function () use ($discount) {
            // remove the discount from the parent
            $discountParent = $discount->parent();

            // get the parent document
            $document = null;
            if ($discountParent instanceof LineItem) {
                $document = $discountParent->parent();
            } elseif ($discountParent instanceof ReceivableDocument) {
                $document = $discountParent;
            }

            // If the document does not exist, is closed, or paid
            // then the discount should not be removed because it
            // would change the total. Instead, simply remove
            // the discount expiration date.
            if (!$document instanceof ReceivableDocument || $document->closed || ($document instanceof Invoice && $document->paid)) {
                $discount->expires = null;
                $discount->save();

                return false;
            }

            // delete the discount
            return $discount->delete();
        });
    }
}
