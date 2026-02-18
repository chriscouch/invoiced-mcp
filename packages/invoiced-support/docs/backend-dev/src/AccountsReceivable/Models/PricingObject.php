<?php

namespace App\AccountsReceivable\Models;

use App\Companies\Traits\MoneyTrait;
use App\Core\Multitenant\Models\MultitenantModel;
use App\Core\Multitenant\TenantContextFacade;
use App\Core\Orm\Event\AbstractEvent;
use App\Core\Orm\Exception\ListenerException;
use App\Core\Orm\Property;
use App\Core\Orm\Traits\AutoTimestamps;
use App\Core\Orm\Type;
use App\Core\RestApi\Traits\ApiObjectTrait;
use App\Core\Utils\RandomString;
use App\Metadata\Interfaces\MetadataModelInterface;
use App\Metadata\Traits\MetadataTrait;

/**
 * @property int    $internal_id
 * @property string $id
 * @property bool   $archived
 * @property string $currency
 */
abstract class PricingObject extends MultitenantModel implements MetadataModelInterface
{
    use ApiObjectTrait;
    use AutoTimestamps;
    use MetadataTrait;
    use MoneyTrait;

    const ID_LENGTH = 6;

    private static array $currentObjects = [];
    private static array $latestObjects = [];

    protected static function getIDProperties(): array
    {
        return ['internal_id'];
    }

    protected function initialize(): void
    {
        self::creating([static::class, 'generateUniqueId']);
        self::creating([static::class, 'uniqueId']);
        self::creating([static::class, 'inheritCurrency']);
        self::created([static::class, 'setAsCurrent']);

        parent::initialize();
    }

    protected static function autoDefinitionPricingObject(): array
    {
        return [
            'internal_id' => new Property(
                type: Type::INTEGER,
                mutable: Property::IMMUTABLE,
                in_array: false,
            ),
            'id' => new Property(
                mutable: Property::MUTABLE_CREATE_ONLY,
                required: true,
                validate: ['callable', 'fn' => [self::class, 'validateID']],
            ),
            'archived' => new Property(
                type: Type::BOOLEAN,
                default: false,
                in_array: false,
            ),
        ];
    }

    //
    // Hooks
    //

    /**
     * Sets the id to a random string if not present.
     */
    public static function generateUniqueId(AbstractEvent $event): void
    {
        /** @var self $model */
        $model = $event->getModel();
        if (!$model->id) {
            $model->id = RandomString::generate(self::ID_LENGTH, RandomString::CHAR_ALNUM);
        }
    }

    /**
     * Verifies the user-chosen ID is unique.
     */
    public static function uniqueId(AbstractEvent $event): void
    {
        /** @var self $model */
        $model = $event->getModel();

        if ($id = $model->id) {
            $n = static::where('id', $id)
                ->where('tenant_id', $model->tenant_id)
                ->where('archived', false)
                ->count();

            if ($n > 0) {
                throw new ListenerException('An item already exists with ID: '.$id, ['field' => 'id']);
            }
        }
    }

    /**
     * Inherits the currency from the company, if not specified.
     */
    public static function inheritCurrency(AbstractEvent $event): void
    {
        // fall back to company currency if none given
        /** @var self $model */
        $model = $event->getModel();
        if (!$model->currency) {
            $model->currency = $model->tenant()->currency;
        }
    }

    public static function setAsCurrent(AbstractEvent $event): void
    {
        /** @var self $model */
        $model = $event->getModel();
        $model::setCurrent($model);
    }

    //
    // Mutators
    //

    protected function setCurrencyValue(?string $currency): ?string
    {
        if (!$currency) {
            return $currency;
        }

        return strtolower($currency);
    }

    //
    // Validators
    //

    /**
     * Validates the external, user-supplied ID.
     */
    public static function validateID(mixed $id): bool
    {
        if (!is_string($id)) {
            return false;
        }

        // Allowed characters: a-z, A-Z, 0-9, _, -, :
        // Min length: 2
        return preg_match('/^[a-z0-9_\-\:]{2,}$/i', $id) > 0;
    }

    //
    // Getters
    //

    /**
     * Sets the current version of a pricing object.
     */
    public static function setCurrent(self $object): void
    {
        $tenant = TenantContextFacade::get()->get();
        $k = static::class.'_'.$tenant->id().'_'.$object->id;
        self::$currentObjects[$k] = &$object;
        self::$latestObjects[$k] = &$object;
    }

    /**
     * Gets the current version of a pricing object.
     *
     * @param string $id
     *
     * @return static
     */
    public static function getCurrent($id): ?self
    {
        if (!$id || !self::validateID($id)) {
            return null;
        }

        // if the current version of a pricing object is not
        // set then look it up from the DB
        $tenant = TenantContextFacade::get()->get();
        $k = static::class.'_'.$tenant->id().'_'.$id;
        if (!array_key_exists($k, self::$currentObjects)) {
            $object = static::where('id', $id)
                ->where('archived', false)
                ->oneOrNull();

            self::$currentObjects[$k] = $object;
        }

        return self::$currentObjects[$k];
    }

    /**
     * Gets the latest version of a pricing object,
     * which might be deleted if there is no current
     * version.
     *
     * @return static
     */
    public static function getLatest(string $id): ?self
    {
        if (!self::validateID($id)) {
            return null;
        }

        // if the current version of a pricing object is not
        // set then look it up from the DB
        $tenant = TenantContextFacade::get()->get();
        $k = static::class.'_'.$tenant->id().'_'.$id;
        if (!array_key_exists($k, self::$latestObjects)) {
            $object = static::where('id', $id)
                ->sort('internal_id DESC')
                ->oneOrNull();

            self::$latestObjects[$k] = $object;
        }

        return self::$latestObjects[$k];
    }

    /**
     * Gets the money formatting options for this object.
     */
    public function moneyFormat(): array
    {
        return $this->tenant()->moneyFormat();
    }

    //
    // Setters
    //

    /**
     * Overrides the delete function and archives this pricing object.
     */
    public function delete(): bool
    {
        return $this->archive();
    }

    /**
     * Archives this pricing object.
     */
    public function archive(): bool
    {
        $this->archived = true;

        $tenant = TenantContextFacade::get()->get();
        $k = static::class.'_'.$tenant->id().'_'.$this->id;
        unset(self::$currentObjects[$k]);

        return $this->save();
    }
}
