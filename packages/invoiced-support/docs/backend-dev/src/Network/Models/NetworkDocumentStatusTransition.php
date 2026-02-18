<?php

namespace App\Network\Models;

use App\Companies\Models\Company;
use App\Companies\Models\Member;
use App\Network\Enums\DocumentStatus;
use DateTimeInterface;
use App\Core\Orm\Model;
use App\Core\Orm\Property;
use App\Core\Orm\Traits\AutoTimestamps;
use App\Core\Orm\Type;

/**
 * @property int               $id
 * @property NetworkDocument   $document
 * @property DocumentStatus    $status
 * @property DateTimeInterface $effective_date
 * @property Company           $company
 * @property Member|null       $member
 * @property string|null       $description
 */
class NetworkDocumentStatusTransition extends Model
{
    use AutoTimestamps;

    protected static function getProperties(): array
    {
        return [
            'document' => new Property(
                belongs_to: NetworkDocument::class,
            ),
            'status' => new Property(
                type: Type::ENUM,
                enum_class: DocumentStatus::class,
            ),
            'effective_date' => new Property(
                type: Type::DATE,
            ),
            'company' => new Property(
                belongs_to: Company::class,
            ),
            'member' => new Property(
                null: true,
                belongs_to: Member::class,
            ),
            'description' => new Property(
                null: true,
            ),
        ];
    }
}
