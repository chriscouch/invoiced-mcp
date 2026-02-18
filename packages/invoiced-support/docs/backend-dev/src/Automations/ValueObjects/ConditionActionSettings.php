<?php

namespace App\Automations\ValueObjects;

use App\Automations\Exception\AutomationException;
use App\Core\Utils\Enums\ObjectType;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Symfony\Component\ExpressionLanguage\SyntaxError;

class ConditionActionSettings extends AbstractActionObjectTypeSettings
{
    public function __construct(
        string $object_type,
        public string $expression
    ) {
        parent::__construct($object_type);
    }

    public function validate(ObjectType $sourceObject): void
    {
        try {
            (new ExpressionLanguage())->lint($this->expression, [$this->object_type]);
        } catch (SyntaxError) {
            throw new AutomationException('Invalid expression');
        }
    }
}
