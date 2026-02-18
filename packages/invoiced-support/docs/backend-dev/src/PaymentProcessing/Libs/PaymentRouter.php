<?php

namespace App\PaymentProcessing\Libs;

use App\AccountsReceivable\Models\Customer;
use App\AccountsReceivable\Models\ReceivableDocument;
use App\PaymentProcessing\Models\MerchantAccount;
use App\PaymentProcessing\Models\PaymentMethod;
use InvalidArgumentException;

/**
 * Tool for routing payments with the correct gateway.
 */
class PaymentRouter
{
    /**
     * Returns a merchant account based on a list of documents, customer settings and payment method.
     *
     * @param ReceivableDocument[] $documents
     *
     * @throws InvalidArgumentException
     */
    public function getMerchantAccount(PaymentMethod $method, ?Customer $customer = null, array $documents = [], bool $throws = false): ?MerchantAccount
    {
        // look for account based on documents
        foreach ($documents as $document) {
            // get merchant account of the first suitable document
            $account = $document->getMerchantAccount($method);
            if (null !== $account) {
                return $account;
            }
        }

        // look for account based on customer
        if ($customer && $account = $customer->merchantAccount($method->id)) {
            return $account;
        }

        // get default account
        try {
            return $method->getDefaultMerchantAccount();
        } catch (InvalidArgumentException $e) {
            if ($throws) {
                throw $e;
            }
        }

        return null;
    }

    /**
     * Returns a gateway based on a list of documents, customer settings and payment method.
     *
     * @param ReceivableDocument[] $documents
     */
    public function getGateway(PaymentMethod $method, ?Customer $customer = null, array $documents = []): ?string
    {
        return $this->getMerchantAccount($method, $customer, $documents)?->gateway;
    }
}
