<?php

namespace App\AccountsReceivable\EmailVariables;

use App\Core\Utils\Enums\ObjectType;
use App\AccountsReceivable\Libs\InvoiceCalculator;
use App\AccountsReceivable\Models\ReceivableDocument;
use App\Sending\Email\Interfaces\EmailVariablesInterface;
use App\Sending\Email\Models\EmailTemplate;

class DocumentEmailVariables implements EmailVariablesInterface
{
    public function __construct(protected ReceivableDocument $document)
    {
    }

    public function generate(EmailTemplate $template): array
    {
        $company = $this->document->tenant();
        $companyVariables = $company->getEmailVariables();

        $customer = $this->document->customer();
        $customerVariables = $customer->getEmailVariables();

        // calculate the invoice so we have access to the computed rates
        $calculatedInvoice = InvoiceCalculator::calculate($this->document->currency, $this->document->items(), $this->document->discounts(), $this->document->taxes(), $this->document->shipping());

        // total up the discounts
        $discounts = 0;
        foreach ($calculatedInvoice->discounts as $discount) {
            $discounts += $discount['amount'];
        }

        $type = ObjectType::fromModel($this->document)->typeName();

        $variables = [
            'url' => $this->document->url,
            'total' => $this->document->currencyFormat($this->document->total),
            'discounts' => $this->document->currencyFormat($discounts),
            'notes' => $this->document->notes,
            $type => [
                'metadata' => (array) $this->document->metadata,
            ],
            'customer' => [
                'metadata' => (array) $customer->metadata,
                'id' => $customer->id,
            ],
        ];

        return array_replace(
            $companyVariables->generate($template),
            $customerVariables->generate($template),
            $variables
        );
    }

    public function getCurrency(): string
    {
        return $this->document->currency;
    }
}
