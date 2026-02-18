<?php

namespace App\Integrations\Xero\Readers;

use App\Integrations\AccountingSync\Traits\PaymentReaderTrait;

/**
 * This syncs the Payment object type. On Xero payments can
 * be represented as a Payment or BatchPayment object.
 */
class XeroPaymentReader extends AbstractXeroReader
{
    use PaymentReaderTrait;
}
