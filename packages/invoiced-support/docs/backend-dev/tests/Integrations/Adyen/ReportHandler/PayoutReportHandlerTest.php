<?php

namespace App\Tests\Integrations\Adyen\ReportHandler;

use App\Core\Utils\RandomString;
use App\Integrations\Adyen\AdyenClient;
use App\Integrations\Adyen\Operations\SaveAdyenPayout;
use App\Integrations\Adyen\ReportHandler\PayoutReportHandler;
use App\PaymentProcessing\Enums\MerchantAccountTransactionType;
use App\PaymentProcessing\Enums\PayoutStatus;
use App\PaymentProcessing\Gateways\AdyenGateway;
use App\PaymentProcessing\Models\MerchantAccount;
use App\PaymentProcessing\Models\MerchantAccountTransaction;
use App\PaymentProcessing\Models\Payout;
use Carbon\CarbonImmutable;
use Mockery;

class PayoutReportHandlerTest extends AbstractReportHandlerTest
{
    private static Payout $payout;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasMerchantAccount(AdyenGateway::ID);
    }

    protected function getHandler(): PayoutReportHandler
    {
        $createPayout = Mockery::mock(SaveAdyenPayout::class);
        $createPayout->shouldReceive('save')
            ->andReturn(self::$payout)
            ->once();

        $adyenClient = Mockery::mock(AdyenClient::class);

        return new PayoutReportHandler(
            self::getService('test.tenant'),
            $createPayout,
            $adyenClient,
        );
    }

    public function testHandleRow(): void
    {
        $merchantAccount = $this->createMerchantAccount('112741-maxnczmria');
        $paymentIds = ['38E9MJ66CWJB41B7', '38EABD66CRNRZVKD', '38E9JO66CRNTCY2S'];
        foreach ($paymentIds as $id) {
            $this->createTransaction($merchantAccount, $id);
        }
        $payout = $this->createPayout($merchantAccount);
        self::$payout = $payout;

        // Process all the reports
        $this->handleFile('balanceplatform_payout_report_custom.csv');

        // Validate that the transactions were associated with the new payout
        foreach ($paymentIds as $id) {
            $transaction = MerchantAccountTransaction::where('merchant_account_id', $merchantAccount)
                ->where('reference', $id)
                ->oneOrNull();
            $this->assertInstanceOf(MerchantAccountTransaction::class, $transaction);
            $this->assertEquals($payout->id, $transaction->payout?->id);
        }
    }

    private function createPayout(MerchantAccount $merchantAccount): Payout
    {
        $payout = new Payout();
        $payout->merchant_account = $merchantAccount;
        $payout->reference = RandomString::generate();
        $payout->currency = 'usd';
        $payout->amount = 100;
        $payout->pending_amount = 0;
        $payout->gross_amount = 100;
        $payout->description = 'Payout';
        $payout->status = PayoutStatus::Completed;
        $payout->bank_account_name = 'Chase *1234';
        $payout->initiated_at = CarbonImmutable::now();
        $payout->saveOrFail();

        return $payout;
    }

    private function createTransaction(MerchantAccount $merchantAccount, string $reference): MerchantAccountTransaction
    {
        $transaction = new MerchantAccountTransaction();
        $transaction->merchant_account = $merchantAccount;
        $transaction->reference = $reference;
        $transaction->type = MerchantAccountTransactionType::Payment;
        $transaction->currency = 'usd';
        $transaction->amount = 100;
        $transaction->fee = 0;
        $transaction->net = 100;
        $transaction->description = 'Payment';
        $transaction->available_on = CarbonImmutable::now();
        $transaction->saveOrFail();

        return $transaction;
    }
}
