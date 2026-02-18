<?php

namespace App\Tests\Exports\Exporters;

use App\Exports\Interfaces\ExporterInterface;
use App\Exports\Libs\ExportStorage;
use App\PaymentProcessing\Enums\DisputeStatus;
use App\PaymentProcessing\Gateways\AdyenGateway;
use App\PaymentProcessing\Gateways\FlywireGateway;
use App\PaymentProcessing\Models\Charge;
use App\PaymentProcessing\Models\Dispute;

class DisputeExporterTest extends AbstractCsvExporterTest
{
    private static Dispute $dispute;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::hasMerchantAccount(AdyenGateway::ID);
        self::hasCustomer();

        $charge = new Charge();
        $charge->customer = self::$customer;
        $charge->currency = 'usd';
        $charge->amount = 100;
        $charge->status = Charge::PENDING;
        $charge->gateway = FlywireGateway::ID;
        $charge->gateway_id = 'PTU146221637';
        $charge->last_status_check = 0;
        $charge->saveOrFail();

        $dispute = new Dispute();
        $dispute->charge = $charge;
        $dispute->amount = 100;
        $dispute->currency = 'usd';
        $dispute->status = DisputeStatus::Undefended;
        $dispute->gateway = AdyenGateway::ID;
        $dispute->gateway_id = '1234';
        $dispute->reason = 'Fraudulent';
        $dispute->saveOrFail();
        self::$dispute = $dispute;
    }

    public function testBuild(): void
    {
        $options = [];
        $expected = 'created_at,currency,amount,gateway,gateway_id,status,reason,defense_reason,charge_id
'.date('Y-m-d', self::$dispute->created_at).',usd,100,flywire_payments,1234,6,Fraudulent,,'.self::$dispute->charge_id.'
';
        $this->verifyBuild($expected, $options);
    }

    protected function getExporter(ExportStorage $storage): ExporterInterface
    {
        return $this->getExporterById('dispute_csv', $storage);
    }
}
