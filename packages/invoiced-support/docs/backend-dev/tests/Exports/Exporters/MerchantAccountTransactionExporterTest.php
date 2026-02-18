<?php

namespace App\Tests\Exports\Exporters;

use App\Core\Utils\RandomString;
use App\Exports\Interfaces\ExporterInterface;
use App\Exports\Libs\ExportStorage;
use App\PaymentProcessing\Enums\MerchantAccountTransactionType;
use App\PaymentProcessing\Enums\PayoutStatus;
use App\PaymentProcessing\Gateways\AdyenGateway;
use App\PaymentProcessing\Models\MerchantAccountTransaction;
use App\PaymentProcessing\Models\Payout;
use Carbon\CarbonImmutable;

class MerchantAccountTransactionExporterTest extends AbstractCsvExporterTest
{
    private static MerchantAccountTransaction $merchantAccountTransaction;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::hasMerchantAccount(AdyenGateway::ID);

        $payout = new Payout();
        $payout->merchant_account = self::$merchantAccount;
        $payout->reference = 'ABC123';
        $payout->currency = 'usd';
        $payout->amount = 100;
        $payout->pending_amount = 0;
        $payout->gross_amount = 100;
        $payout->description = 'Payout';
        $payout->status = PayoutStatus::Completed;
        $payout->bank_account_name = 'Chase *1234';
        $payout->initiated_at = CarbonImmutable::now();
        $payout->saveOrFail();

        $transaction = new MerchantAccountTransaction();
        $transaction->merchant_account = self::$merchantAccount;
        $transaction->reference = RandomString::generate();
        $transaction->type = MerchantAccountTransactionType::Payment;
        $transaction->currency = 'usd';
        $transaction->amount = 100;
        $transaction->fee = 0;
        $transaction->net = 100;
        $transaction->description = 'Payment';
        $transaction->available_on = CarbonImmutable::now();
        $transaction->payout = $payout;
        $transaction->saveOrFail();
        self::$merchantAccountTransaction = $transaction;
    }

    public function testBuild(): void
    {
        $options = [];
        $expected = 'reference,type,description,available_on,currency,amount,fee,net,source_type,source_id,payout.reference
'.self::$merchantAccountTransaction->reference.',payment,Payment,'.self::$merchantAccountTransaction->available_on->format('Y-m-d').',usd,100,0,100,,,ABC123
';
        $this->verifyBuild($expected, $options);
    }

    protected function getExporter(ExportStorage $storage): ExporterInterface
    {
        return $this->getExporterById('merchant_account_transaction_csv', $storage);
    }
}
