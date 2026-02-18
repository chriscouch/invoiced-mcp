<?php

namespace App\Automations\Actions;

use App\Automations\Enums\AutomationResult;
use App\Automations\Exception\AutomationException;
use App\Automations\Libs\NormalizerFactory;
use App\Automations\ValueObjects\AutomationContext;
use App\Automations\ValueObjects\AutomationOutcome;
use App\Automations\ValueObjects\CreateObjectActionSettings;
use App\Core\Multitenant\Models\MultitenantModel;
use App\Core\Templating\TwigRendererFactory;
use App\Core\Utils\Enums\ObjectType;
use App\Core\Orm\Exception\ModelException;
use Symfony\Contracts\Translation\TranslatorInterface;
use Throwable;

class CreateObjectAction extends AbstractAutomationAction
{
    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly NormalizerFactory $factory,
        private readonly TwigRendererFactory $rendererFactory,
    ) {
    }

    protected function getAction(): string
    {
        return 'CreateObject';
    }

    public function getValue(mixed $field, array $fieldMapping, array $variables, AutomationContext $context): mixed
    {
        return ($fieldMapping['normalizer'] ?? null)
            ? $this->factory->get($fieldMapping['normalizer'], $field->value)
            : trim($this->rendererFactory->render(
                $field->value,
                $variables,
                $context->getTwigContext($this->translator)
            ));
    }

    private function setDecoratedValue(AutomationContext $context, MultitenantModel $object, array $mappingFields, array $fields, mixed $field, array $variables): void
    {
        $mappingField = $mappingFields[$field->name] ?? [];
        $value = $this->getValue($field, $mappingField, $variables, $context);
        if (isset($mappingField['setter'])) {
            $value = $this->marshalValue($mappingField['type'] ?? null, $value);
            $object->{$mappingField['setter']}($value);
        } else {
            $this->setValue($object, $fields, $field->name, $value);
        }
    }

    public function perform(object $settings, AutomationContext $context): AutomationOutcome
    {
        $mapping = new CreateObjectActionSettings($settings->object_type, $settings->fields);
        $modelClass = ObjectType::fromTypeName($mapping->object_type)->modelClass();
        $variables = $context->getVariables();
        /** @var MultitenantModel $object */
        $object = new $modelClass();

        $mappingFields = $mapping->getAvailableFields($mapping->object_type);
        $fields = $mapping->getSubjectFields();
        try {
            foreach ($mapping->getPreSaveFields() as $field) {
                $this->setDecoratedValue($context, $object, $mappingFields, $fields, $field, $variables);
            }

            $object->saveOrFail();

            foreach ($mapping->getPostSaveFields() as $field) {
                $this->setDecoratedValue($context, $object, $mappingFields, $fields, $field, $variables);
            }
        } catch (ModelException $e) {
            throw new AutomationException($e->getMessage());
        } catch (Throwable $e) {
            throw new AutomationException('Failed to save '.$object::modelName().': '.$e->getMessage());
        }

        return new AutomationOutcome(AutomationResult::Succeeded);
    }

    public function validateSettings(object $settings, ObjectType $sourceObject): object
    {
        if (!isset($settings->object_type)) {
            throw new AutomationException('Missing new object type');
        }
        if (!isset($settings->fields) || !is_array($settings->fields) || 0 === count($settings->fields)) {
            throw new AutomationException('Missing mapping for fields');
        }

        $this->validate($settings->object_type);

        $mapping = new CreateObjectActionSettings($settings->object_type, $settings->fields);
        $mapping->validate($sourceObject);

        return $mapping->serialize();
    }
}
