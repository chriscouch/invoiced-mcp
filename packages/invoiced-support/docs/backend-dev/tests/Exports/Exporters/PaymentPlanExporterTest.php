<?php

namespace App\Tests\Exports\Exporters;

use App\Exports\Interfaces\ExporterInterface;
use App\Exports\Libs\ExportStorage;
use App\PaymentPlans\Models\PaymentPlan;
use App\PaymentPlans\Models\PaymentPlanInstallment;

class PaymentPlanExporterTest extends AbstractCsvExporterTest
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::hasCustomer();
        self::hasInvoice();

        $installment1 = new PaymentPlanInstallment();
        $installment1->date = (int) mktime(0, 0, 0, 3, 12, 2019);
        $installment1->amount = 50;
        $installment2 = new PaymentPlanInstallment();
        $installment2->date = (int) mktime(0, 0, 0, 4, 12, 2019);
        $installment2->amount = 50;
        $paymentPlan = new PaymentPlan();
        $paymentPlan->installments = [
            $installment1,
            $installment2,
        ];
        self::$invoice->attachPaymentPlan($paymentPlan, false, true);
    }

    public function testBuild(): void
    {
        $opts = [
            'start' => strtotime('-1 month'),
            'end' => strtotime('+1 month'),
        ];
        $expected = 'customer.name,customer.number,number,currency,total,balance,autopay,next_payment_attempt,payment_attempts,payment_plan.status,installment.date,installment.amount,installment.balance
Sherlock,CUST-00001,INV-00001,usd,100,100,0,,0,active,2019-03-12,50,50
,,,,,,,,,,2019-04-12,50,50
';
        $this->verifyBuild($expected, $opts);
    }

    protected function getExporter(ExportStorage $storage): ExporterInterface
    {
        return $this->getExporterById('payment_plan_csv', $storage);
    }
}
