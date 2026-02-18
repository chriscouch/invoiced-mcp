<?php

namespace App\Tests\PaymentProcessing\Views\Payment;

use App\PaymentProcessing\Forms\PaymentFormBuilder;
use App\PaymentProcessing\Models\PaymentMethod;

class CardPaymentViewTest extends PaymentViewTestBase
{
    const METHOD = PaymentMethod::CREDIT_CARD;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::acceptsCreditCards('authorizenet');
    }

    protected function cleanViewParameters(array $params): array
    {
        $params = parent::cleanViewParameters($params);
        unset($params['countries']);

        return $params;
    }

    protected function expectedViewParameters(PaymentFormBuilder $form, PaymentMethod $paymentMethod): array
    {
        return array_replace(parent::expectedViewParameters($form, $paymentMethod), [
            'isTestGateway' => true,
            'hasBillingAddress' => true,
            'address' => [
                'name' => 'Sherlock',
                'address1' => 'Test',
                'address2' => 'Address',
                'city' => 'Austin',
                'state' => 'TX',
                'postal_code' => '78701',
                'country' => 'US',
            ],
        ]);
    }


    public function testShouldBeShown(): void
    {
        $method = PaymentMethod::instance(self::$company, static::METHOD);
        $view = $this->getView($method);
        $form = $this->getFormBuilder()->build();
        $merchantAccount = self::$merchantAccount ?? null;
        $this->assertFalse($view->shouldBeShown($form, $method, $merchantAccount));
    }
}
