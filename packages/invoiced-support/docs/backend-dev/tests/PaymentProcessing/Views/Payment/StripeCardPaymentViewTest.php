<?php

namespace App\Tests\PaymentProcessing\Views\Payment;

use App\PaymentProcessing\Forms\PaymentFormBuilder;
use App\PaymentProcessing\Gateways\StripeGateway;
use App\PaymentProcessing\Interfaces\PaymentViewInterface;
use App\PaymentProcessing\Models\PaymentMethod;
use App\PaymentProcessing\ValueObjects\PaymentFormCapabilities;

class StripeCardPaymentViewTest extends PaymentViewTestBase
{
    const METHOD = PaymentMethod::CREDIT_CARD;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::acceptsCreditCards(StripeGateway::ID);
        self::hasMerchantAccount(StripeGateway::ID, 'TEST_MERCHANT_ID', ['key' => 'sk_test', 'publishable_key' => 'pk_test']);
    }

    protected function getView(PaymentMethod $method): PaymentViewInterface
    {
        $method->gateway = 'stripe';

        return parent::getView($method);
    }

    public function testGetPaymentFormCapabilities(): void
    {
        $method = PaymentMethod::instance(self::$company, static::METHOD);
        $view = $this->getView($method);
        $this->assertEquals(new PaymentFormCapabilities(
            isSubmittable: true,
            supportsVaulting: true,
            supportsConvenienceFee: true,
            hasReceiptEmail: true,
        ), $view->getPaymentFormCapabilities());
    }

    protected function cleanViewParameters(array $params): array
    {
        $params = parent::cleanViewParameters($params);
        unset($params['countries']);

        return $params;
    }

    protected function expectedViewParameters(PaymentFormBuilder $form, PaymentMethod $paymentMethod): array
    {
        return [
            'publishableKey' => 'pk_test',
            'clientSecret' => null,
            'defaultValues' => null,
        ];
    }
}
