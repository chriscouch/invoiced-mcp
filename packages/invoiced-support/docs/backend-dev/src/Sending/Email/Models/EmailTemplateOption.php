<?php

namespace App\Sending\Email\Models;

use App\Core\Multitenant\Models\MultitenantModel;
use App\Core\Orm\Property;

/**
 * @property string $template
 * @property string $option
 * @property string $value
 */
class EmailTemplateOption extends MultitenantModel
{
    const SEND_ON_SUBSCRIPTION_INVOICE = 'send_on_subscription_invoice';
    const SEND_ON_AUTOPAY_INVOICE = 'send_on_autopay_invoice';
    const CUTOFF_DATE = 'invoice_start_date';
    const SEND_ON_SUBSCRIBE = 'send_on_subscribe';
    const SEND_ON_CANCELLATION = 'send_on_cancellation';
    const BUTTON_TEXT = 'button_text';
    const ATTACH_PDF = 'attach_pdf';
    const ATTACH_SECONDARY_FILES = 'attach_secondary_files';
    const SEND_ONCE_PAID = 'send_on_paid';
    const DAYS_BEFORE_BILLING = 'days_before_renewal';
    const SEND_ON_CHARGE = 'send_on_charge';
    const SEND_ON_ISSUE = 'send_on_issue';
    const SEND_REMINDER_DAYS = 'send_reminder_every_days';

    protected static function getIDProperties(): array
    {
        return ['tenant_id', 'template', 'option'];
    }

    protected static function getProperties(): array
    {
        return [
            'template' => new Property(
                mutable: Property::MUTABLE_CREATE_ONLY,
                required: true,
                relation: EmailTemplate::class,
            ),
            'option' => new Property(
                required: true,
            ),
            'value' => new Property(),
        ];
    }
}
