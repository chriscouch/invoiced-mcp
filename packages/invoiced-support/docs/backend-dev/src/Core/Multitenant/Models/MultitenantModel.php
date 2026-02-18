<?php

namespace App\Core\Multitenant\Models;

use App\Companies\Models\Company;
use App\Core\Multitenant\Exception\MultitenantException;
use App\Core\Multitenant\MultitenantModelListener;
use App\Core\Multitenant\TenantContextFacade;
use App\Core\Orm\Model;
use App\Core\Orm\Property;
use App\Core\Orm\Query;
use App\Core\Orm\Type;

/**
 * @property int $tenant_id
 */
abstract class MultitenantModel extends Model
{
    const TENANT_COLUMN = 'tenant_id';

    private array $_relationshipsCache = [];

    //
    // Setup
    //

    protected function initialize(): void
    {
        // install the multitenant listener for this model
        MultitenantModelListener::add($this);

        parent::initialize();
    }

    protected static function autoDefinitionMultiTenant(): array
    {
        return [
            static::TENANT_COLUMN => new Property(
                type: Type::INTEGER,
                mutable: Property::MUTABLE_CREATE_ONLY,
                required: true,
                in_array: false,
                relation: Company::class,
            ),
        ];
    }

    //
    // Queries
    //

    /**
     * @return Query<static>
     */
    public static function query(): Query
    {
        return self::queryWithCurrentTenant();
    }

    /**
     * Builds a model query constrained to the current tenant.
     *
     * @throws MultitenantException when the current tenant is not set
     *
     * @return Query<static>
     */
    public static function queryWithCurrentTenant(): Query
    {
        $tenantContext = TenantContextFacade::get();
        if (!$tenantContext->has()) {
            throw new MultitenantException('Attempted to query '.static::modelName().' without setting the current tenant on the DI container!');
        }

        // add a constraint to the current tenant to the where statement
        return self::queryWithTenant($tenantContext->get());
    }

    /**
     * Builds a model query constrained to a specified tenant.
     *
     * @return Query<static>
     */
    public static function queryWithTenant(Company $tenant): Query
    {
        return self::getBlankQuery()->where(static::TENANT_COLUMN, $tenant);
    }

    /**
     * Builds a model query without the multi-tenancy constraint.
     * WARNING: this should only be used by background processes
     * that need to access data across multiple companies.
     *
     * @return Query<static>
     */
    public static function queryWithoutMultitenancyUnsafe(): Query
    {
        return self::getBlankQuery();
    }

    /**
     * Gets a blank query object for this model that has not been modified.
     *
     * @return Query<static>
     */
    public static function getBlankQuery(): Query
    {
        $model = new static(); /* @phpstan-ignore-line */
        $query = new Query($model);

        return static::customizeBlankQuery($query);
    }

    /**
     * Placeholder for models to customize blank queries. This method
     * serves as a default when that method is not implemented.
     *
     * @return Query<static>
     */
    public static function customizeBlankQuery(Query $query): Query
    {
        // do nothing
        return $query;
    }

    /**
     * Gets the tenant owning this model.
     */
    public function tenant(): Company
    {
        // check if the current tenant is set to save a DB call
        $tenantContext = TenantContextFacade::get();
        if ($tenantContext->has()) {
            $tenant = $tenantContext->get();
            $tenantId = $this->tenant_id;
            if ($tenantId == $tenant->id() || !$tenantId) {
                return $tenant;
            }
        }

        /** @var Company|null $tenant */
        $tenant = $this->relation('tenant_id');
        if (!$tenant) {
            throw new MultitenantException('Called tenant() on multitenant model that does not have a tenant specified.');
        }

        return $tenant;
    }

    /**
     * Gets the model object corresponding to a relation.
     *
     * This overrides the insecure Pulsar implementation. In this
     * implementation the model will actually be queried from
     * the database, and will return if none exists. The query
     * uses the multitenant system, which maintains the integrity
     * of the system. This is not strictly compatible with the
     * Pulsar contract since this can also return null, however,
     * an error would be preferred to returning invalid models,
     * or worse, a model belonging to another account.
     */
    public function relation(string $name): Model|null
    {
        $id = $this->$name;
        if (!$id) {
            return null;
        }

        // has many relationship
        if (is_array($id)) {
            return $id;
        }

        $k = $name.'.'.$id;

        if (!isset($this->_relationshipsCache[$k])) {
            if ($class = static::definition()->get($name)?->relation) {
                $foreignKey = 'id';
                $this->_relationshipsCache[$k] = $class::where($foreignKey, $id)->oneOrNull();
            }
        }

        return $this->_relationshipsCache[$k];
    }

    public function setRelation(string $name, Model $model): static
    {
        $id = $this->$name;
        if (!$id) {
            return $this;
        }

        $k = $name.'.'.$id;

        $this->_relationshipsCache[$k] = $model;

        return $this;
    }
}
