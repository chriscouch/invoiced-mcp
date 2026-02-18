<?php

namespace App\Metadata\Libs;

use App\Core\Multitenant\TenantContextFacade;
use App\Metadata\Exception\MetadataStorageException;
use App\Metadata\Interfaces\MetadataModelInterface;
use App\Core\Orm\Query;

class MetadataQuery
{
    /**
     * Adds metadata conditions to an ORM query.
     *
     * @throws MetadataStorageException
     */
    public static function addTo(Query $query, array $conditions): void
    {
        if (0 == count($conditions)) {
            return;
        }

        $modelClass = $query->getModel();
        /** @var MetadataModelInterface $model */
        $model = new $modelClass();
        $storage = $model->getMetadataReader();
        $tenantId = (int) TenantContextFacade::get()->get()->id();

        foreach ($storage->buildSqlConditions($conditions, $model, $tenantId) as $sql) {
            $query->where($sql);
        }
    }
}
