<?php

namespace App\AccountsReceivable\Models;

use App\Core\RestApi\Traits\ApiObjectTrait;
use App\Core\Multitenant\Models\MultitenantModel;
use App\Core\Orm\Property;
use App\Core\Orm\Type;
use App\Core\Utils\Enums\ObjectType;
use App\Core\Utils\ModelNormalizer;
use App\ActivityLog\Interfaces\EventObjectInterface;
use App\ActivityLog\Traits\EventObjectTrait;

/**
 * This model represents an instance of a customer viewing a document.
 *
 * @property int    $id
 * @property string $document_type
 * @property int    $document_id
 * @property int    $timestamp
 * @property string $user_agent
 * @property string $ip
 */
class DocumentView extends MultitenantModel implements EventObjectInterface
{
    use ApiObjectTrait;
    use EventObjectTrait;

    private ReceivableDocument $_document;

    protected static function getProperties(): array
    {
        return [
            'document_type' => new Property(
                validate: ['enum', 'choices' => ['credit_note', 'estimate', 'invoice']],
                in_array: false,
            ),
            'document_id' => new Property(
                type: Type::INTEGER,
                in_array: false,
            ),
            'timestamp' => new Property(
                type: Type::DATE_UNIX,
                required: true,
                validate: 'timestamp',
                default: 'now',
            ),
            'user_agent' => new Property(
                required: true,
            ),
            'ip' => new Property(
                required: true,
                validate: 'ip',
            ),
        ];
    }

    /**
     * Gets the owning document.
     */
    public function document(): ReceivableDocument
    {
        if (!isset($this->_document)) {
            $type = $this->document_type;
            if (ObjectType::Invoice->typeName() === $type) {
                $this->_document = new Invoice(['id' => $this->document_id]);
            } elseif (ObjectType::Estimate->typeName() === $type) {
                $this->_document = new Estimate(['id' => $this->document_id]);
            } elseif (ObjectType::CreditNote->typeName() === $type) {
                $this->_document = new CreditNote(['id' => $this->document_id]);
            }
        }

        return $this->_document;
    }

    //
    // EventObjectInterface
    //

    public function getEventAssociations(): array
    {
        return [
            [$this->document_type, $this->document_id],
        ];
    }

    public function getEventObject(): array
    {
        $result = ModelNormalizer::toArray($this);
        $document = $this->document();
        $documentType = ObjectType::fromModel($document)->typeName();
        $result[$documentType] = $document->getEventObject();

        return $result;
    }
}
