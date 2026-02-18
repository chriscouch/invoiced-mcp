<?php

namespace App\ActivityLog\Libs\Messages;

use App\ActivityLog\ValueObjects\AttributedObject;
use App\ActivityLog\ValueObjects\AttributedString;
use App\Sending\Email\Models\EmailTemplate;

class EmailMessage extends BaseMessage
{
    private static array $emailNames = [
        EmailTemplate::NEW_INVOICE => 'Invoice',
        EmailTemplate::UNPAID_INVOICE => 'Invoice Reminder',
        EmailTemplate::LATE_PAYMENT_REMINDER => 'Past Due Invoice',
        EmailTemplate::PAID_INVOICE => 'Thank You',
        EmailTemplate::PAYMENT_PLAN => 'Payment Plan',
        EmailTemplate::ESTIMATE => 'Estimate',
        EmailTemplate::CREDIT_NOTE => 'Credit Note',
        EmailTemplate::PAYMENT_RECEIPT => 'Payment Receipt',
        EmailTemplate::REFUND => 'Refund',
        EmailTemplate::STATEMENT => 'Statement',
        EmailTemplate::SUBSCRIPTION_CONFIRMATION => 'Subscription Confirmation',
        EmailTemplate::SUBSCRIPTION_CANCELED => 'Cancellation Confirmation',
        EmailTemplate::AUTOPAY_FAILED => 'Failed Payment Alert',
        EmailTemplate::SUBSCRIPTION_BILLED_SOON => 'Subscription Billed Soon Notice',
    ];

    private static array $emailTypes = [
        EmailTemplate::NEW_INVOICE => 'invoice',
        EmailTemplate::UNPAID_INVOICE => 'invoice',
        EmailTemplate::LATE_PAYMENT_REMINDER => 'invoice',
        EmailTemplate::PAID_INVOICE => 'invoice',
        EmailTemplate::PAYMENT_PLAN => 'invoice',
        EmailTemplate::ESTIMATE => 'estimate',
        EmailTemplate::CREDIT_NOTE => 'credit_note',
        EmailTemplate::PAYMENT_RECEIPT => 'transaction',
        EmailTemplate::REFUND => 'transaction',
        EmailTemplate::STATEMENT => 'customer',
        EmailTemplate::SUBSCRIPTION_CONFIRMATION => 'subscription',
        EmailTemplate::SUBSCRIPTION_CANCELED => 'subscription',
        EmailTemplate::AUTOPAY_FAILED => 'subscription',
        EmailTemplate::SUBSCRIPTION_BILLED_SOON => 'subscription',
    ];

    protected function emailSent(): array
    {
        $email = $this->email();

        return [
            $this->customer('customerName'),
            new AttributedString(' was emailed '.$this->an($email->value).' '),
            $email,
        ];
    }

    protected function emailNotSent(): array
    {
        // add the recipient address to the event message
        $recipient = '';
        if ($this->email && $address = $this->email->email) {
            $recipient = ' ('.$address.')';
        }

        $email = $this->email();

        return [
            new AttributedString('Failed to send '),
            $this->customer('customerName'),
            new AttributedString($recipient.' '.$this->an($email->value).' '),
            $email,
        ];
    }

    /**
     * Builds an attributed value for the email associated
     * with this message.
     */
    private function email(): AttributedObject
    {
        $template = array_value($this->object, 'template');
        if (!$template) {
            return new AttributedObject('email', 'Email', []);
        }

        $type = array_value(self::$emailTypes, $template);
        $name = array_value(self::$emailNames, $template);
        if (!$name) {
            // look up the template
            $emailTemplate = EmailTemplate::where('id', $template)->oneOrNull();
            if ($emailTemplate instanceof EmailTemplate) {
                $name = $emailTemplate->name;
            } else {
                $name = 'Email';
            }
        }

        return new AttributedObject('email', $name, [
            'object' => $type,
            'object_id' => $this->associations[$type] ?? null,
        ]);
    }
}
