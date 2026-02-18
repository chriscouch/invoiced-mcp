<?php

namespace App\Integrations\AccountingSync\Models;

use App\Core\Multitenant\Models\MultitenantModel;
use App\Integrations\Enums\IntegrationType;
use App\Core\Orm\Property;
use App\Core\Orm\Type;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Symfony\Component\ExpressionLanguage\SyntaxError;

/**
 * @property int             $id
 * @property IntegrationType $integration
 * @property string          $object_type
 * @property string          $formula
 * @property bool            $enabled
 */
class AccountingSyncReadFilter extends MultitenantModel
{
    protected static function getProperties(): array
    {
        return [
            'integration' => new Property(
                type: Type::ENUM,
                enum_class: IntegrationType::class,
            ),
            'object_type' => new Property(),
            'formula' => new Property(
                required: true,
                validate: ['callable', 'fn' => [self::class, 'validateFormula']],
            ),
            'enabled' => new Property(
                type: Type::BOOLEAN,
            ),
        ];
    }

    public static function validateFormula(mixed $input, array $options, self $filter): bool
    {
        try {
            (new ExpressionLanguage())->compile($input, ['record']);

            return true;
        } catch (SyntaxError $e) {
            $filter->getErrors()->add('Invalid formula: '.$e->getMessage(), ['field' => 'formula']);

            return false;
        }
    }
}
