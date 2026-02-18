<?php

namespace App\Sending\Email\Libs;

use App\AccountsReceivable\Models\Customer;
use App\Sending\Email\Interfaces\EmailVariablesInterface;
use App\Sending\Email\Models\EmailTemplate;

class EmailVariablesCollection implements EmailVariablesInterface
{
    /** @var EmailVariablesInterface[] */
    private array $variables;

    public function __construct(Customer $customer, private readonly string $currency)
    {
        $company = $customer->tenant();
        $this->variables = [
            $company->getEmailVariables(),
            $customer->getEmailVariables(),
        ];
    }

    public function addVariables(EmailVariablesInterface $variables): void
    {
        $this->variables[] = $variables;
    }

    public function generate(EmailTemplate $template): array
    {
        return array_merge(...array_map(fn ($variables) => $variables->generate($template), $this->variables));
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }
}
