<?php

namespace App\PaymentProcessing\Traits;

use App\AccountsReceivable\Models\Customer;
use App\Companies\Models\Company;
use App\PaymentProcessing\Models\PaymentInstruction;
use App\PaymentProcessing\Models\PaymentMethod;

/**
 * Payment method by country decorator.
 */
trait PaymentFormTrait
{
    /**
     * @return PaymentMethod[]
     */
    protected function getDefaultMethods(Company $company, ?Customer $customer = null): array
    {
        $methods = PaymentMethod::allEnabled($company);

        if ($customer) {
            /** @var PaymentInstruction[] $overrides */
            $overrides = PaymentInstruction::where('country', $customer->country)->all();
            foreach ($overrides as $override) {
                $methodId = $override->payment_method_id;
                if ($override->enabled) {
                    if (!isset($methods[$methodId])) {
                        $methods[$methodId] = new PaymentMethod();
                        $methods[$methodId]->id = $methodId;
                    }
                    $methods[$methodId]->meta = $override->meta;
                    $methods[$methodId]->enabled = $override->enabled;
                    continue;
                }
                unset($methods[$methodId]);
            }
        }

        return $methods;
    }
}
