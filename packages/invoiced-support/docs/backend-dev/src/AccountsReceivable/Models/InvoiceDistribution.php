<?php

namespace App\AccountsReceivable\Models;

use App\Core\Multitenant\Models\MultitenantModel;
use App\Sending\Email\Models\EmailTemplate;
use App\Core\Orm\Exception\ListenerException;
use App\Core\Orm\Event\AbstractEvent;
use App\Core\Orm\Property;
use App\Core\Orm\Traits\AutoTimestamps;
use App\Core\Orm\Type;

/**
 * @deprecated
 *
 * @property int         $id
 * @property int         $invoice_id
 * @property string|null $department
 * @property string|null $template
 * @property bool        $enabled
 */
class InvoiceDistribution extends MultitenantModel
{
    use AutoTimestamps;

    protected static function getProperties(): array
    {
        return [
            'invoice_id' => new Property(
                type: Type::INTEGER,
                required: true,
                in_array: false,
                relation: Invoice::class,
            ),
            'department' => new Property(
                null: true,
            ),
            'template' => new Property(
                null: true,
                relation: EmailTemplate::class,
            ),
            'enabled' => new Property(
                type: Type::BOOLEAN,
                default: true,
            ),
        ];
    }

    protected function initialize(): void
    {
        self::creating([self::class, 'validateRelations']);

        parent::initialize();
    }

    /**
     * Validates model relations.
     */
    public static function validateRelations(AbstractEvent $event): void
    {
        /** @var self $model */
        $model = $event->getModel();

        $invoice = $model->relation('invoice_id');
        if (!$invoice) {
            throw new ListenerException("No such invoice: {$model->invoice_id}", ['field' => 'invoice']);
        }

        $emailTemplateId = $model->template;
        $emailTemplate = $model->relation('template');
        if ($emailTemplateId && !$emailTemplate) {
            throw new ListenerException("No such email template: $emailTemplateId", ['field' => 'template']);
        }
    }

    public function setInvoice(Invoice $invoice): void
    {
        $this->invoice_id = (int) $invoice->id();
        $this->setRelation('invoice_id', $invoice);
    }
}
