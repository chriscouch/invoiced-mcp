<?php

namespace App\Core\RestApi\Routes;

use App\Core\RestApi\Exception\InvalidRequest;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\QueryParameter;
use App\Core\Orm\Definition;
use App\Core\Orm\Error;
use App\Core\Orm\Model;
use App\Core\Orm\Relation\Relationship;
use ICanBoogie\Inflector;

/**
 * @template T
 */
abstract class AbstractModelApiRoute extends AbstractApiRoute
{
    private ?string $modelId = null;
    private ?array $modelIds = null;
    /** @var T|null */
    protected $model;
    /** @var class-string<T> */
    private string $modelClass;

    protected function getBaseQueryParameters(): array
    {
        return [
            'expand' => new QueryParameter(
                types: ['string', 'array'],
                default: [],
            ),
            'exclude' => new QueryParameter(
                types: ['string', 'array'],
                default: [],
            ),
            'include' => new QueryParameter(
                types: ['string', 'array'],
                default: [],
            ),
        ];
    }

    /**
     * Sets the model ID.
     *
     * @return $this
     */
    public function setModelId(?string $id): self
    {
        $this->modelId = $id;

        return $this;
    }

    /**
     * Sets the array model ID.
     */
    public function setModelIds(array $ids): void
    {
        $this->modelIds = $ids;
        $this->modelId = implode(',', $ids);
    }

    /**
     * Gets the model ID.
     */
    public function getModelId(): ?string
    {
        return $this->modelId;
    }

    /**
     * Sets the model for this route.
     *
     * @param T $model
     *
     * @return $this
     */
    public function setModel(Model $model): self
    {
        $this->modelClass = $model::class;
        $this->model = $model;

        return $this;
    }

    /**
     * Sets the model class for this route.
     *
     * @param class-string<T> $modelClass
     *
     * @return $this
     */
    public function setModelClass(string $modelClass): self
    {
        $this->modelClass = $modelClass;
        if (!$this->model) {
            $this->model = new $modelClass();
        }

        return $this;
    }

    /**
     * Gets the model for this route.
     *
     * @return T
     */
    public function getModel(): Model
    {
        return $this->model;
    }

    /**
     * Gets the first error off the error stack.
     */
    public function getFirstError(): ?Error
    {
        if (!$this->model) {
            return null;
        }

        $errors = $this->model->getErrors();

        return (count($errors) > 0) ? $errors[0] : null;
    }

    /**
     * Builds a validation error from a CRUD operation.
     */
    protected function modelValidationError(Error $error): InvalidRequest
    {
        $code = ('no_permission' == $error->getError()) ? 403 : 400;
        $param = array_value($error->getContext(), 'field');

        return new InvalidRequest($error->getMessage(), $code, $param);
    }

    /**
     * Retrieves the model associated on this class with
     * the persisted version from the data layer.
     *
     * @throws InvalidRequest if the model cannot be found
     *
     * @return T
     */
    public function retrieveModel(ApiCallContext $context)
    {
        if ($this->model?->persisted()) {
            return $this->model;
        }

        if (!isset($this->modelClass)) {
            throw $this->requestNotRecognizedError($context->request);
        }

        $id = $this->modelIds ?: $this->modelId;
        $this->model = $this->getModelOrFail($this->modelClass, $id);

        return $this->model;
    }

    /**
     * @template M
     *
     * @param class-string<M> $modelClass
     *
     * @throws InvalidRequest
     *
     * @return M
     */
    protected function getModelOrFail(string $modelClass, mixed $id)
    {
        $model = $modelClass::find($id);

        if (!$model) {
            // convert the model name into the humanized version
            $name = $modelClass::modelName();
            $inflector = Inflector::get();
            $name = $inflector->titleize($inflector->underscore($name));

            throw new InvalidRequest($name.' was not found: '.$id, 404);
        }

        return $model;
    }

    /**
     * Builds a model permission error.
     */
    protected function permissionError(): InvalidRequest
    {
        return new InvalidRequest('You do not have permission to do that', 403);
    }

    /**
     * Builds a model 404 error.
     */
    protected function modelNotFoundError(): InvalidRequest
    {
        return new InvalidRequest($this->getModelName().' was not found: '.$this->modelId, 404);
    }

    /**
     * Converts the IDs in the request into models. If
     * a model cannot be found then it will throw an invalid
     * request error.
     *
     * @throws InvalidRequest
     */
    protected function hydrateRelationships(array $parameters): array
    {
        /** @var Definition $definition */
        $definition = $this->model::definition(); /* @phpstan-ignore-line */
        foreach ($parameters as $key => &$value) {
            $property = $definition->get($key);
            if (!$property) {
                continue;
            }

            if (Relationship::BELONGS_TO != $property->relation_type) {
                continue;
            }

            // this behavior is not compatible with the legacy belongs-to relationship type
            // which is identified as a local key property that is persisted to the DB
            if ($property->persisted) {
                continue;
            }

            if ($property->null && null === $value) {
                continue;
            }

            if ($value instanceof Model) {
                continue;
            }

            /** @var Model $type */
            $type = $property->relation;
            $model = $type::find($value);
            if (!$model) {
                throw new InvalidRequest($this->humanClassName($type).' was not found: '.$value);
            }

            $value = $model;
        }

        return $parameters;
    }

    protected function getModelName(): string
    {
        if ($this->model) {
            // convert the model name into the humanized version
            $name = $this->model::modelName();
            $inflector = Inflector::get();

            return $inflector->titleize($inflector->underscore($name));
        }

        return $this->humanClassName($this->modelClass);
    }

    /**
     * Generates the human name for a class
     * i.e. LineItem -> Line Item.
     *
     * @param string|object $class
     */
    public function humanClassName($class): string
    {
        // get the class name if an object is given
        if (is_object($class)) {
            $class = $class::class;
        }

        // split the class name up by namespaces
        $namespace = explode('\\', $class);
        $className = end($namespace);

        // convert the class name into the humanized version
        $inflector = Inflector::get();

        return $inflector->titleize($inflector->underscore($className));
    }

    protected function isParameterIncluded(ApiCallContext $context, string $key): bool
    {
        $include = $context->queryParameters['include'] ?? [];
        if (is_string($include)) {
            $include = explode(',', $include);
        }

        return in_array($key, $include);
    }

    protected function isParameterExcluded(ApiCallContext $context, string $key): bool
    {
        $exclude = $context->queryParameters['exclude'] ?? [];
        if (is_string($exclude)) {
            $exclude = explode(',', $exclude);
        }

        return in_array($key, $exclude);
    }
}
