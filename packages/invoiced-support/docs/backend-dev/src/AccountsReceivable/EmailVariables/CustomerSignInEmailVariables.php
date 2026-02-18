<?php

namespace App\AccountsReceivable\EmailVariables;

use App\CustomerPortal\Command\SignInCustomer;
use App\CustomerPortal\Libs\JWTLoginLinkGenerator;
use App\Sending\Email\Interfaces\EmailVariablesInterface;
use App\Sending\Email\Libs\EmailHtml;
use App\Sending\Email\Models\EmailTemplate;

class CustomerSignInEmailVariables extends CustomerEmailVariables implements EmailVariablesInterface
{
    public function generate(EmailTemplate $template): array
    {
        $variables = parent::generate($template);
        $variables['company_name'] = $this->customer->tenant()->name;
        $variables['sign_in_button'] = EmailHtml::button('Sign In', (new JWTLoginLinkGenerator())->generateLoginUrl($this->customer->tenant(), $this->customer, SignInCustomer::TEMPORARY_SIGNED_IN_TTL));

        return $variables;
    }
}
