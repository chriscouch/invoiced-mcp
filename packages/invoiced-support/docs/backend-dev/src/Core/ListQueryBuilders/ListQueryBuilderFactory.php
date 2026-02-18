<?php

namespace App\Core\ListQueryBuilders;

use App\Companies\Models\Company;
use App\Core\Orm\Model;
use InvalidArgumentException;
use Symfony\Component\DependencyInjection\ServiceLocator;

readonly class ListQueryBuilderFactory
{
    public function __construct(private ServiceLocator $handlerLocator)
    {
    }

    /**
     * Gets an exporter instance of a given type.
     *
     * @throws InvalidArgumentException
     */
    public function get(string $type, Company $company, array $options): AbstractListQueryBuilder
    {
        if ($this->handlerLocator->has($type)) {
            $builder = $this->handlerLocator->get($type);
        } else {
            $builder = $this->handlerLocator->get(Model::class);
        }

        /* @var AbstractListQueryBuilder $builder */
        $builder->setQueryClass($type);
        $builder->setCompany($company);
        $builder->setOptions($options);
        $builder->initialize();

        return $builder;
    }
}
