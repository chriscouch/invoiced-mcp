<?php

namespace App\Tests\PaymentProcessing\Views\Payment;

use App\PaymentProcessing\Forms\PaymentFormBuilder;
use App\PaymentProcessing\Models\PaymentMethod;
use App\PaymentProcessing\ValueObjects\PaymentFormCapabilities;

class AchPaymentViewTest extends PaymentViewTestBase
{
    const METHOD = PaymentMethod::ACH;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::acceptsACH();
    }

    public function testGetPaymentFormCapabilities(): void
    {
        $method = PaymentMethod::instance(self::$company, static::METHOD);
        $view = $this->getView($method);
        $this->assertEquals(new PaymentFormCapabilities(
            isSubmittable: true,
            supportsVaulting: true,
            supportsConvenienceFee: false,
            hasReceiptEmail: true,
        ), $view->getPaymentFormCapabilities());
    }

    protected function cleanViewParameters(array $params): array
    {
        $params = parent::cleanViewParameters($params);
        unset($params['achForm']);
        unset($params['merchantAccount']);
        unset($params['countries']);

        return $params;
    }

    protected function expectedViewParameters(PaymentFormBuilder $form, PaymentMethod $paymentMethod): array
    {
        return [
            'accountHolderType' => 'company',
            'achDebitTerms' => null,
            'isTestGateway' => true,
            'address' => [
                'name' => 'Sherlock',
                'address1' => 'Test',
                'address2' => 'Address',
                'city' => 'Austin',
                'state' => 'TX',
                'postal_code' => '78701',
                'country' => 'US',
            ],
        ];
    }
}
