<?php

namespace App\Tests\PaymentProcessing\Views\Payment;

use App\PaymentProcessing\Forms\PaymentFormBuilder;
use App\PaymentProcessing\Gateways\StripeGateway;
use App\PaymentProcessing\Models\PaymentMethod;
use App\PaymentProcessing\ValueObjects\PaymentFormCapabilities;

class StripeAchPaymentViewTest extends PaymentViewTestBase
{
    const METHOD = PaymentMethod::ACH;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasMerchantAccount(StripeGateway::ID, 'TEST_MERCHANT_ID', ['key' => 'sk_test', 'publishable_key' => 'pk_test']);
        self::acceptsACH(StripeGateway::ID);
    }

    public function testGetPaymentFormCapabilities(): void
    {
        $method = PaymentMethod::instance(self::$company, static::METHOD);
        $view = $this->getView($method);
        $this->assertEquals(new PaymentFormCapabilities(
            isSubmittable: false,
            supportsVaulting: true,
            supportsConvenienceFee: false,
            hasReceiptEmail: false,
        ), $view->getPaymentFormCapabilities());
    }

    protected function expectedViewParameters(PaymentFormBuilder $form, PaymentMethod $paymentMethod): array
    {
        return array_replace(parent::expectedViewParameters($form, $paymentMethod), [
            'clientId' => self::$customer->client_id,
            'methodId' => 'ach',
            'subdomain' => self::$company->username,
        ]);
    }
}
