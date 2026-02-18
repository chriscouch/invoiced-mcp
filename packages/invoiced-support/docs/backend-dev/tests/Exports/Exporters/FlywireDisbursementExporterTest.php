<?php

namespace App\Tests\Exports\Exporters;

use App\Core\I18n\ValueObjects\Money;
use App\Exports\Interfaces\ExporterInterface;
use App\Exports\Libs\ExportStorage;
use App\Integrations\Flywire\Enums\FlywirePaymentStatus;
use App\Integrations\Flywire\Enums\FlywireRefundStatus;
use App\Integrations\Flywire\Models\FlywireDisbursement;
use App\Integrations\Flywire\Models\FlywirePayment;
use App\Integrations\Flywire\Models\FlywirePayout;
use App\Integrations\Flywire\Models\FlywireRefund;
use App\PaymentProcessing\Gateways\FlywireGateway;
use Carbon\CarbonImmutable;
use DateTimeInterface;

class FlywireDisbursementExporterTest extends AbstractCsvExporterTest
{
    private static FlywireDisbursement $flywireDisbursement;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::hasMerchantAccount(FlywireGateway::ID);

        $disbursement = new FlywireDisbursement();
        $disbursement->disbursement_id = 'ABC123';
        $disbursement->setAmount(new Money('USD', 100));
        $disbursement->bank_account_number = '123456789';
        $disbursement->recipient_id = 'QQQ';
        $disbursement->status_text = 'pending';
        $disbursement->delivered_at = CarbonImmutable::now();
        $disbursement->saveOrFail();
        self::$flywireDisbursement = $disbursement;

        $payment = new FlywirePayment();
        $payment->merchant_account = self::$merchantAccount;
        $payment->payment_id = 'ABC123';
        $payment->recipient_id = 'UUO';
        $payment->initiated_at = CarbonImmutable::now();
        $payment->setAmountFrom(new Money('USD', 100));
        $payment->setAmountTo(new Money('USD', 100));
        $payment->status = FlywirePaymentStatus::Initiated;
        $payment->saveOrFail();

        $payout = new FlywirePayout();
        $payout->payout_id = 'PAY123';
        $payout->payment = $payment;
        $payout->status_text = 'random';
        $payout->setAmount(new Money('USD', 99));
        $payout->disbursement = $disbursement;
        $payout->saveOrFail();

        $refund = new FlywireRefund();
        $refund->refund_id = 'ABC1234';
        $refund->recipient_id = 'UUO';
        $refund->initiated_at = CarbonImmutable::now();
        $refund->setAmount(new Money('USD', 100));
        $refund->setAmountTo(new Money('USD', 100));
        $refund->status = FlywireRefundStatus::Initiated;
        $refund->disbursement = $disbursement;
        $refund->saveOrFail();
    }

    public function testBuild(): void
    {
        $options = [];
        $expected = 'disbursement_id,recipient_id,delivered_at,status_text,bank_account_number,amount,currency,payment.payment_id,payment.amount,refund.refund_id,refund.amount_to
ABC123,QQQ,'.self::$flywireDisbursement->delivered_at?->format(DateTimeInterface::ATOM).',pending,123456789,1,usd,ABC123,0.99,,
,,,,,,,,,ABC1234,1
';
        $this->verifyBuild($expected, $options);
    }

    protected function getExporter(ExportStorage $storage): ExporterInterface
    {
        return $this->getExporterById('flywire_disbursement_csv', $storage);
    }
}
