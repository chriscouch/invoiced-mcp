<?php

namespace App\PaymentProcessing\Traits;

use App\Companies\Models\Company;
use App\PaymentProcessing\Models\PaymentFlow;
use App\PaymentProcessing\Models\TokenizationFlow;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

trait PaymentFlowUrlTrait
{
    protected function getPaymentFlowCanceledUrl(Company $company, PaymentFlow $paymentFlow): string
    {
        return $this->urlGenerator->generate(
            'customer_portal_payment_flow_canceled',
            [
                'subdomain' => $company->getSubdomainUsername(),
                'id' => $paymentFlow->identifier,
            ],
            UrlGeneratorInterface::ABSOLUTE_URL
        );
    }

    protected function getPaymentFlowCompletedUrl(Company $company, PaymentFlow $paymentFlow): string
    {
        return $this->urlGenerator->generate(
            'customer_portal_payment_flow_complete',
            [
                'subdomain' => $company->getSubdomainUsername(),
                'id' => $paymentFlow->identifier,
            ],
            UrlGeneratorInterface::ABSOLUTE_URL
        );
    }

    protected function getTokenizationCompletedUrl(Company $company, TokenizationFlow $flow): string
    {
        return $this->urlGenerator->generate(
            'customer_portal_tokenization_flow_complete',
            [
                'subdomain' => $company->getSubdomainUsername(),
                'id' => $flow->identifier,
            ],
            UrlGeneratorInterface::ABSOLUTE_URL
        );
    }
}
