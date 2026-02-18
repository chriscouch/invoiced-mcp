<?php

namespace App\PaymentProcessing\Interfaces;

use App\AccountsReceivable\Models\Customer;
use App\AccountsReceivable\Models\ReceivableDocument;
use App\Core\I18n\ValueObjects\Money;
use App\PaymentProcessing\Exceptions\ChargeException;
use App\PaymentProcessing\Exceptions\PaymentSourceException;
use App\PaymentProcessing\Models\MerchantAccount;
use App\PaymentProcessing\Models\PaymentSource;
use App\PaymentProcessing\ValueObjects\ChargeValueObject;
use App\PaymentProcessing\ValueObjects\SourceValueObject;

/**
 * This interface describes the methods for vaulting, charging and
 * deleting payment information stored on a payment gateway.
 */
interface PaymentSourceVaultInterface
{
    /**
     * Stores a payment source on the payment gateway.
     *
     * @throws PaymentSourceException when the saving the payment source fails
     */
    public function vaultSource(Customer $customer, MerchantAccount $account, array $parameters): SourceValueObject;

    /**
     * Charges a payment source stored on the payment gateway.
     *
     * On a successful collection run this method must build and
     * save a Transaction for the given invoice.
     *
     * @param ReceivableDocument[] $documents
     *
     * @throws ChargeException
     */
    public function chargeSource(PaymentSource $source, Money $amount, array $parameters, string $description, array $documents = []): ChargeValueObject;

    /**
     * Deletes the provided payment source from the payment gateway.
     * Note: there is no need to call $source->delete() because this
     * method is being called from inside of the delete() method.
     *
     * @throws PaymentSourceException when the delete fails
     */
    public function deleteSource(MerchantAccount $account, PaymentSource $source): void;
}
