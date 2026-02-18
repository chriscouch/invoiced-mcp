<?php

namespace App\SubscriptionBilling\EmailVariables;

use App\Sending\Email\Interfaces\EmailVariablesInterface;
use App\Sending\Email\Libs\EmailHtml;
use App\Sending\Email\Models\EmailTemplate;
use App\Sending\Email\Models\EmailTemplateOption;
use App\SubscriptionBilling\Models\Subscription;

/**
 * View model for subscription email templates.
 */
class SubscriptionEmailVariables implements EmailVariablesInterface
{
    public function __construct(protected Subscription $subscription)
    {
    }

    public function generate(EmailTemplate $template): array
    {
        $plan = $this->subscription->plan();
        $company = $this->subscription->tenant();

        $frequency = $plan->toString();

        $variables = [
            // subscription specific variables
            'name' => $plan->getCustomerFacingName(),
            'recurring_total' => $plan->currencyFormat($this->subscription->recurring_total).' '.$frequency,
            'frequency' => $frequency,
            'start_date' => date($company->date_format, $this->subscription->start_date),
        ];

        if (EmailTemplate::SUBSCRIPTION_CANCELED != $template->id) {
            $url = $this->subscription->url;
            $buttonText = $template->getOption(EmailTemplateOption::BUTTON_TEXT);

            $variables['manage_subscription_button'] = EmailHtml::button($buttonText, $url);
            $variables['url'] = $url;
        }

        if (EmailTemplate::SUBSCRIPTION_BILLED_SOON == $template->id) {
            $variables['time_until_renewal'] = $this->subscription->billingPeriods()->billsIn();
        }

        $companyVariables = $company->getEmailVariables();
        $customerVariables = $this->subscription->customer()->getEmailVariables();

        return array_replace(
            $companyVariables->generate($template),
            $customerVariables->generate($template),
            $variables
        );
    }

    public function getCurrency(): string
    {
        return $this->subscription->plan()->currency;
    }
}
