<?php

namespace App\Tests\PaymentProcessing\Views\Payment;

use App\PaymentProcessing\Forms\PaymentFormBuilder;
use App\PaymentProcessing\Gateways\GoCardlessGateway;
use App\PaymentProcessing\Models\PaymentMethod;
use App\PaymentProcessing\ValueObjects\PaymentFormCapabilities;

class GoCardlessPaymentViewTest extends PaymentViewTestBase
{
    const METHOD = PaymentMethod::DIRECT_DEBIT;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasMerchantAccount(GoCardlessGateway::ID);
        self::acceptsDirectDebit();
    }

    public function testGetPaymentFormCapabilities(): void
    {
        $method = PaymentMethod::instance(self::$company, static::METHOD);
        $view = $this->getView($method);
        $this->assertEquals(new PaymentFormCapabilities(
            isSubmittable: false,
            supportsVaulting: false,
            supportsConvenienceFee: false,
            hasReceiptEmail: false,
        ), $view->getPaymentFormCapabilities());
    }

    protected function expectedViewParameters(PaymentFormBuilder $form, PaymentMethod $paymentMethod): array
    {
        return array_replace(parent::expectedViewParameters($form, $paymentMethod), [
            'clientId' => self::$customer->client_id,
            'subdomain' => self::$company->username,
        ]);
    }
}
