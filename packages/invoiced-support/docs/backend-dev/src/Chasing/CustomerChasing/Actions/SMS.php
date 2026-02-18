<?php

namespace App\Chasing\CustomerChasing\Actions;

use App\AccountsReceivable\Models\Contact;
use App\Chasing\ValueObjects\ActionResult;
use App\Chasing\ValueObjects\ChasingEvent;
use App\Core\I18n\MoneyFormatter;
use App\Sending\Sms\Exceptions\SendSmsException;
use App\Sending\Sms\Libs\TextMessageSender;

/**
 * Chasing action to send text messages
 * to open accounts.
 */
class SMS extends AbstractAction
{
    public function __construct(
        private TextMessageSender $sender,
    ) {
    }

    public function execute(ChasingEvent $event): ActionResult
    {
        $customer = $event->getCustomer();
        $smsTemplate = $event->getStep()->smsTemplate();
        if (!$smsTemplate) {
            return new ActionResult(false, 'SMS template not found');
        }

        $variables = $this->getVariables($event);

        $contacts = Contact::where('customer_id', $customer->id)
            ->where('sms_enabled')
            ->first(5);

        $to = [];
        foreach ($contacts as $contact) {
            $to[] = [
                'name' => $contact->name,
                'phone' => $contact->phone,
                'country' => $contact->country,
            ];
        }

        try {
            $this->sender->send($customer, null, $to, $smsTemplate->message, $variables, $smsTemplate->template_engine);
        } catch (SendSmsException $e) {
            return new ActionResult(false, $e->getMessage());
        }

        return new ActionResult(true);
    }

    public function getVariables(ChasingEvent $event): array
    {
        $customer = $event->getCustomer();
        $company = $customer->tenant();

        $moneyFormat = $customer->moneyFormat();
        $formatter = MoneyFormatter::get();

        return [
            'company_name' => $company->getDisplayName(),
            'customer_name' => $customer->name,
            'customer_number' => $customer->number,
            'account_balance' => $formatter->format($event->getBalance(), $moneyFormat),
            'url' => $event->getClientUrl(),
        ];
    }
}
