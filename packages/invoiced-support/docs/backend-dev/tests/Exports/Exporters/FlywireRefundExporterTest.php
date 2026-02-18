<?php

namespace App\Tests\Exports\Exporters;

use App\Core\I18n\ValueObjects\Money;
use App\Exports\Interfaces\ExporterInterface;
use App\Exports\Libs\ExportStorage;
use App\Integrations\Flywire\Enums\FlywireRefundStatus;
use App\Integrations\Flywire\Models\FlywireRefund;
use App\PaymentProcessing\Gateways\FlywireGateway;
use Carbon\CarbonImmutable;
use DateTimeInterface;

class FlywireRefundExporterTest extends AbstractCsvExporterTest
{
    private static FlywireRefund $flywireRefund;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::hasMerchantAccount(FlywireGateway::ID);
        $refund = new FlywireRefund();
        $refund->refund_id = 'ABC1234';
        $refund->recipient_id = 'UUO';
        $refund->initiated_at = CarbonImmutable::now();
        $refund->setAmount(new Money('USD', 100));
        $refund->setAmountTo(new Money('USD', 100));
        $refund->status = FlywireRefundStatus::Initiated;
        $refund->saveOrFail();
        self::$flywireRefund = $refund;
    }

    public function testBuild(): void
    {
        $options = [];
        $expected = 'refund_id,recipient_id,initiated_at,amount,currency,amount_to,currency_to,status
ABC1234,UUO,'.self::$flywireRefund->initiated_at->format(DateTimeInterface::ATOM).',1,usd,1,usd,initiated
';
        $this->verifyBuild($expected, $options);
    }

    protected function getExporter(ExportStorage $storage): ExporterInterface
    {
        return $this->getExporterById('flywire_refund_csv', $storage);
    }
}
