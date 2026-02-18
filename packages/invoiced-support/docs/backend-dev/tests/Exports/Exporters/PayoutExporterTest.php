<?php

namespace App\Tests\Exports\Exporters;

use App\Core\Utils\RandomString;
use App\Exports\Interfaces\ExporterInterface;
use App\Exports\Libs\ExportStorage;
use App\PaymentProcessing\Enums\PayoutStatus;
use App\PaymentProcessing\Gateways\AdyenGateway;
use App\PaymentProcessing\Models\Payout;
use Carbon\CarbonImmutable;
use DateTimeInterface;

class PayoutExporterTest extends AbstractCsvExporterTest
{
    private static Payout $payout;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::hasMerchantAccount(AdyenGateway::ID);

        $payout = new Payout();
        $payout->merchant_account = self::$merchantAccount;
        $payout->reference = RandomString::generate();
        $payout->currency = 'usd';
        $payout->amount = 100;
        $payout->pending_amount = 0;
        $payout->gross_amount = 100;
        $payout->description = 'Payout';
        $payout->status = PayoutStatus::Pending;
        $payout->bank_account_name = 'Chase *1234';
        $payout->initiated_at = CarbonImmutable::now();
        $payout->saveOrFail();
        self::$payout = $payout;
    }

    public function testBuild(): void
    {
        $options = [];
        $expected = 'reference,description,initiated_at,currency,gross_amount,pending_amount,amount,status,bank_account_name,statement_descriptor,arrival_date,failure_message
'.self::$payout->reference.',Payout,'.self::$payout->initiated_at->format(DateTimeInterface::ATOM).',usd,100,0,100,pending,"Chase *1234",,,
';
        $this->verifyBuild($expected, $options);
    }

    protected function getExporter(ExportStorage $storage): ExporterInterface
    {
        return $this->getExporterById('payout_csv', $storage);
    }
}
