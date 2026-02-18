<?php

namespace App\Automations\Actions;

use App\Automations\Enums\AutomationResult;
use App\Automations\Exception\AutomationException;
use App\Automations\Providers\CustomerBalanceExpressionFunctionProvider;
use App\Automations\ValueObjects\AutomationContext;
use App\Automations\ValueObjects\AutomationOutcome;
use App\Automations\ValueObjects\ConditionActionSettings;
use App\Core\Utils\Enums\ObjectType;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Throwable;

class ConditionAction extends AbstractAutomationAction
{
    public function __construct(private readonly CustomerBalanceExpressionFunctionProvider $provider)
    {
    }

    public function perform(object $settings, AutomationContext $context): AutomationOutcome
    {
        $mapping = new ConditionActionSettings($settings->object_type, $settings->expression);
        $objectType = $mapping->object_type;
        $object = $context->getVariables();
        if (isset($object[$objectType])) {
            // customer should be object in order to evaluate expression
            $object[$objectType] = $this->toObjectRecursive($object[$objectType]);
        }

        $result = AutomationResult::Succeeded;

        // we should handle warning as well as errors here
        set_error_handler(function () use (&$result, &$terminate): ?bool { /** @phpstan-ignore-line */
            $result = AutomationResult::Failed;
            $terminate = true;

            return null;
        });

        $el = new ExpressionLanguage();
        $el->registerProvider($this->provider);

        try {
            $terminate = !($el)->evaluate($mapping->expression, $object);
        } catch (Throwable) {
            $result = AutomationResult::Failed;
            $terminate = true;
        }
        restore_error_handler();

        return new AutomationOutcome(
            result: $result,
            terminate: $terminate
        );
    }

    private function toObjectRecursive(array $variables): object
    {
        foreach ($variables as $key => $value) {
            if (is_array($value)) {
                $variables[$key] = $this->toObjectRecursive($value);
            }
        }

        return (object) $variables;
    }

    public function validateSettings(object $settings, ObjectType $sourceObject): object
    {
        if (!isset($settings->object_type)) {
            throw new AutomationException('Missing target object');
        }

        if (!isset($settings->expression)) {
            throw new AutomationException('Missing name object');
        }

        $this->validate($settings->object_type, $sourceObject);

        $mapping = new ConditionActionSettings($settings->object_type, $settings->expression);
        $mapping->validate($sourceObject);

        return $mapping->serialize();
    }

    protected function getAction(): string
    {
        return 'Condition';
    }
}
