<?php

namespace App\Companies\EmailVariables;

use App\Companies\Models\Company;
use App\Sending\Email\Interfaces\EmailVariablesInterface;
use App\Sending\Email\Models\EmailTemplate;

/**
 * View model for company email templates.
 */
class CompanyEmailVariables implements EmailVariablesInterface
{
    public function __construct(protected Company $company)
    {
    }

    public function generate(EmailTemplate $template): array
    {
        return [
            'company_name' => $this->company->getDisplayName(),
            'company_username' => $this->company->username,
            'company_address' => $this->company->address(false, false),
            'company_email' => $this->company->email,
        ];
    }

    public function getCurrency(): string
    {
        return $this->company->currency;
    }
}
