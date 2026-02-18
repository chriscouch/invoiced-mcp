<?php

namespace App\Automations\ValueObjects;

use App\AccountsReceivable\Models\DocumentView;
use App\Automations\AutomationConfiguration;
use App\Automations\Exception\AutomationException;
use App\Automations\Interfaces\AutomationEventInterface;
use App\Automations\Models\AutomationRun;
use App\Automations\Models\AutomationWorkflow;
use App\Core\Multitenant\Models\MultitenantModel;
use App\Core\Orm\Model;
use App\Core\Templating\TwigContext;
use App\Core\Utils\Enums\ObjectType;
use App\Core\Utils\ModelNormalizer;
use App\ActivityLog\Interfaces\EventStorageInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

final class AutomationContext
{
    public array $sourceObjectData = [];
    public ObjectType $objectType;
    private array $automations;

    /**
     * @throws AutomationException
     */
    public function __construct(
        public ?MultitenantModel $sourceObject,
        private readonly AutomationWorkflow $workflow,
        public ?AutomationEventInterface $event = null,
    ) {
        $this->automations = AutomationConfiguration::get()->all();

        // If source is a DocumentView, switch to the underlying document model (e.g. Invoice)
        if ($sourceObject instanceof DocumentView) {
            $sourceObject = $sourceObject->document();
        }

        if ($sourceObject) {
            $this->sourceObject = $sourceObject;
            $this->sourceObjectData = ModelNormalizer::toArray($this->sourceObject);
            $this->objectType = ObjectType::fromModel($sourceObject);
            $this->sourceObjectData = $this->overrideGetters($this->automations[$this->objectType->typeName()], $sourceObject);

            return;
        }

        if ($this->event) {
            $this->sourceObjectData = $this->event->objectData();
            $this->objectType = $this->event->getObjectType();

            return;
        }

        throw new AutomationException('Could not find source object');
    }

    public function getObjectId(): string
    {
        return (string) $this->sourceObjectData['id'];
    }

    public function toArray(): array
    {
        return [
            'object_type' => $this->objectType->typeName(),
            'object_id' => $this->getObjectId(),
            'workflow' => $this->workflow->id,
            'event' => $this->event?->getId(),
        ];
    }

    /**
     * @throws AutomationException
     */
    public static function fromRun(AutomationRun $run, EventStorageInterface $storage): self
    {
        $objectType = $run->object_type;
        $modelClass = $objectType->modelClass();
        $objectId = $run->object_id;
        $sourceObject = $modelClass::find($objectId);

        $event = null;

        if ($run->event) {
            // event should be hydrated after being deleted
            $event = $sourceObject ? $run->event : $run->event->hydrateFromStorage($storage);
        } elseif ($run->event_type_id && $sourceObject instanceof MultitenantModel) {
            $event = new AutomationEvent($sourceObject, $run->event_type_id);
        }

        return new self(
            $sourceObject,
            $run->workflow_version->automation_workflow,
            $event
        );
    }

    public function getVariables(): array
    {
        $typeName = $this->objectType->typeName();
        $variables = [
            $typeName => $this->sourceObjectData,
        ];

        $sourceObject = $this->sourceObject;

        $automations = $this->automations;
        if (isset($automations[$typeName]['associatedActionObjects'])) {
            foreach ($automations[$typeName]['associatedActionObjects'] as $object) {
                // case for deleted item
                if (isset($this->sourceObjectData[$object]) && is_object($this->sourceObjectData[$object])) {
                    $variables[$object] = $this->sourceObjectData[$object];
                    continue;
                }
                if (!$sourceObject) {
                    continue;
                }
                $relation = $sourceObject->relation($object);
                if (!$relation) {
                    $definition = $sourceObject::definition()->get($object);
                    if ($definition?->relation) {
                        $key = $sourceObject->{$definition->local_key};
                        if ('id' === $definition->foreign_key) {
                            $relation = $definition->relation::find($key);
                        } else {
                            $relation = $definition->relation::where($definition->foreign_key, $key)->oneOrNull();
                        }
                    }
                }
                if ($relation) {
                    $variables[$object] = $this->overrideGetters($automations[$object], $relation);
                }
            }
        }

        return $this->toArrayRecursive($variables);
    }

    private function overrideGetters(array $config, Model $relation): array
    {
        $variables = ModelNormalizer::toArray($relation);
        foreach ($config['fields'] as $key => $field) {
            if (!isset($field['getter'])) {
                continue;
            }
            if (method_exists($relation, $field['getter'])) {
                $variables[$key] = $relation->{$field['getter']}();
            }
        }

        return $variables;
    }

    private function toArrayRecursive(array $variables): array
    {
        foreach ($variables as $key => $value) {
            if (is_object($value)) {
                $variables[$key] = (array) $value;
            }
            if (is_array($value)) {
                $variables[$key] = $this->toArrayRecursive($value);
            }
        }

        return $variables;
    }

    public function getTwigContext(TranslatorInterface $translator): TwigContext
    {
        if ($sourceObject = $this->sourceObject) {
            $company = $sourceObject->tenant();

            if (method_exists($sourceObject, 'calculatePrimaryCurrency')) {
                $currency = $sourceObject->calculatePrimaryCurrency();
            } elseif (property_exists($sourceObject, 'currency')) {
                $currency = $sourceObject->currency;
            } else {
                $currency = $company->currency;
            }

            if (method_exists($sourceObject, 'moneyFormat')) {
                $format = $sourceObject->moneyFormat();
            } else {
                $format = $company->moneyFormat();
            }
        } elseif ($this->event) {
            $company = $this->event->tenant();
            $currency = $this->sourceObjectData['currency'] ?? $company->currency;
            $format = $company->moneyFormat();
        } else {
            throw new AutomationException('Could not find source object');
        }

        return new TwigContext($company, $currency, $format, $translator);
    }

    public function tenantId(): ?int
    {
        return $this->sourceObject?->tenant_id ?? $this->event?->tenant()->id;
    }
}
