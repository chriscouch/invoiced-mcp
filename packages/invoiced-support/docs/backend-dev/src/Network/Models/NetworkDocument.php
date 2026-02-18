<?php

namespace App\Network\Models;

use App\Companies\Models\Company;
use App\Core\RestApi\Traits\ApiObjectTrait;
use App\Core\Orm\Model;
use App\Core\Orm\Property;
use App\Core\Orm\Traits\AutoTimestamps;
use App\Core\Orm\Type;
use App\Core\Utils\ModelNormalizer;
use App\ActivityLog\Interfaces\EventObjectInterface;
use App\ActivityLog\Traits\EventObjectTrait;
use App\Network\Enums\DocumentFormat;
use App\Network\Enums\DocumentStatus;
use App\Network\Enums\NetworkDocumentType;
use App\Network\Traits\NetworkConnectionApiTrait;

/**
 * @property int                 $id
 * @property string              $reference
 * @property DocumentFormat      $format
 * @property NetworkDocumentType $type
 * @property string|null         $currency
 * @property float|null          $total
 * @property Company             $from_company
 * @property int                 $from_company_id
 * @property Company             $to_company
 * @property int                 $to_company_id
 * @property int                 $version
 * @property DocumentStatus      $current_status
 */
class NetworkDocument extends Model implements EventObjectInterface
{
    use AutoTimestamps;
    use ApiObjectTrait;
    use EventObjectTrait;
    use NetworkConnectionApiTrait;

    protected static function getProperties(): array
    {
        return [
            'from_company' => new Property(
                belongs_to: Company::class,
            ),
            'from_company_id' => new Property(
                type: Type::INTEGER,
                in_array: false,
            ),
            'to_company_id' => new Property(
                type: Type::INTEGER,
                in_array: false,
            ),
            'to_company' => new Property(
                belongs_to: Company::class,
            ),
            'type' => new Property(
                type: Type::ENUM,
                enum_class: NetworkDocumentType::class,
            ),
            'reference' => new Property(),
            'currency' => new Property(
                null: true,
            ),
            'total' => new Property(
                type: Type::FLOAT,
                null: true,
            ),
            'format' => new Property(
                type: Type::ENUM,
                default: DocumentFormat::UniversalBusinessLanguage,
                enum_class: DocumentFormat::class,
            ),
            'version' => new Property(
                type: Type::INTEGER,
                default: 1,
            ),
            'current_status' => new Property(
                type: Type::ENUM,
                required: true,
                enum_class: DocumentStatus::class,
            ),
        ];
    }

    public function getEventTenantId(): int
    {
        return 0; // the tenant ID is not known.
    }

    public function getEventObject(): array
    {
        $result = ModelNormalizer::toArray($this);
        $result['from_company'] = $this->buildCompanyArray($this->from_company);
        $result['to_company'] = $this->buildCompanyArray($this->to_company);

        return $result;
    }
}
