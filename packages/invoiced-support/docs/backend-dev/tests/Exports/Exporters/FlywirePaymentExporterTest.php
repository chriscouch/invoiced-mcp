<?php

namespace App\Tests\Exports\Exporters;

use App\Core\I18n\ValueObjects\Money;
use App\Exports\Interfaces\ExporterInterface;
use App\Exports\Libs\ExportStorage;
use App\Integrations\Flywire\Enums\FlywirePaymentStatus;
use App\Integrations\Flywire\Models\FlywirePayment;
use App\PaymentProcessing\Gateways\FlywireGateway;
use Carbon\CarbonImmutable;
use DateTimeInterface;

class FlywirePaymentExporterTest extends AbstractCsvExporterTest
{
    private static FlywirePayment $flywirePayment;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::hasMerchantAccount(FlywireGateway::ID);
        $payment = new FlywirePayment();
        $payment->merchant_account = self::$merchantAccount;
        $payment->payment_id = 'ABC123';
        $payment->recipient_id = 'UUO';
        $payment->initiated_at = CarbonImmutable::now();
        $payment->setAmountFrom(new Money('USD', 100));
        $payment->setAmountTo(new Money('USD', 100));
        $payment->status = FlywirePaymentStatus::Initiated;
        $payment->saveOrFail();
        self::$flywirePayment = $payment;
    }

    public function testBuild(): void
    {
        $options = [];
        $expected = 'payment_id,recipient_id,initiated_at,amount_from,amount_to,currency_from,currency_to,status,expiration_date,payment_method_type,payment_method_brand,payment_method_card_classification,payment_method_card_expiration,payment_method_last4,cancellation_reason,reason,reason_code
ABC123,UUO,'.self::$flywirePayment->initiated_at->format(DateTimeInterface::ATOM).',1,1,usd,usd,initiated,,,,,,,,,
';
        $this->verifyBuild($expected, $options);
    }

    protected function getExporter(ExportStorage $storage): ExporterInterface
    {
        return $this->getExporterById('flywire_payment_csv', $storage);
    }
}
