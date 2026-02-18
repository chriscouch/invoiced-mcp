<?php

namespace App\Core\RestApi\Normalizers;

use App\Core\RestApi\Interfaces\NormalizerInterface;
use App\Core\Orm\Model;
use App\Core\Utils\ModelNormalizer;
use stdClass;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Normalizes ORM objects in an API response.
 */
class ModelApiNormalizer implements NormalizerInterface
{
    private array $exclude = [];
    private array $include = [];
    private array $expand = [];

    public function __construct(RequestStack $requestStack)
    {
        $request = $requestStack->getCurrentRequest();
        if (!$request instanceof Request) {
            return;
        }

        // exclude parameter
        $exclude = $request->query->get('exclude');
        if (is_string($exclude) && !empty($exclude)) {
            $exclude = explode(',', $exclude);
        }

        if (is_array($exclude)) {
            $this->setExclude(array_filter($exclude));
        }

        // include parameter
        $include = $request->query->get('include');
        if (is_string($include) && !empty($include)) {
            $include = explode(',', $include);
        }

        if (is_array($include)) {
            $this->setInclude(array_filter($include));
        }

        // expand parameter
        $expand = $request->query->get('expand');
        if (is_string($expand) && !empty($expand)) {
            $expand = explode(',', $expand);
        }

        if (is_array($expand)) {
            $this->setExpand(array_filter($expand));
        }
    }

    public function normalize(mixed $input): ?array
    {
        // serialize a collection of models
        if (is_array($input)) {
            $models = [];
            foreach ($input as $model) {
                // skip serialization if we are not dealing with models or stdClass objects
                if (!($model instanceof Model)) {
                    if ($model instanceof stdClass) {
                        $models[] = json_decode((string) json_encode($model), true);

                        continue;
                    }

                    return null;
                }

                $models[] = ModelNormalizer::toArray(
                    model: $model,
                    exclude: $this->exclude,
                    include: $this->include,
                    expand: $this->expand
                );
            }

            return $models;
        }

        // serialize a single model
        if ($input instanceof Model) {
            return ModelNormalizer::toArray(
                model: $input,
                exclude: $this->exclude,
                include: $this->include,
                expand: $this->expand
            );
        }

        return null;
    }

    /**
     * Sets properties to be excluded.
     */
    public function setExclude(array $exclude): self
    {
        $this->exclude = $exclude;

        return $this;
    }

    /**
     * Gets properties to be excluded.
     */
    public function getExclude(): array
    {
        return $this->exclude;
    }

    /**
     * Sets properties to be included.
     */
    public function setInclude(array $include): self
    {
        $this->include = $include;

        return $this;
    }

    /**
     * Gets properties to be included.
     */
    public function getInclude(): array
    {
        return $this->include;
    }

    /**
     * Sets properties to be expanded.
     */
    public function setExpand(array $expand): self
    {
        $this->expand = $expand;

        return $this;
    }

    /**
     * Gets properties to be expanded.
     */
    public function getExpand(): array
    {
        return $this->expand;
    }
}
