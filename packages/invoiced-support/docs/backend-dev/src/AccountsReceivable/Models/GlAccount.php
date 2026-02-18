<?php

namespace App\AccountsReceivable\Models;

use App\Core\Multitenant\Models\MultitenantModel;
use App\Core\Orm\Event\AbstractEvent;
use App\Core\Orm\Exception\ListenerException;
use App\Core\Orm\Property;
use App\Core\Orm\Query;
use App\Core\Orm\Traits\AutoTimestamps;
use App\Core\Orm\Type;

/**
 * @property string   $name
 * @property string   $code
 * @property int|null $parent_id
 * @property string   $sort_key
 */
class GlAccount extends MultitenantModel
{
    use AutoTimestamps;

    const MAX_LEVELS = 5;

    //
    // Model Overrides
    //

    protected static function getProperties(): array
    {
        return [
            'name' => new Property(
                required: true,
                validate: ['string', 'min' => 2, 'max' => 100],
            ),
            'code' => new Property(
                required: true,
                validate: [
                    ['callable', 'fn' => [self::class, 'validateCode']],
                    ['unique', 'column' => 'code'],
                ],
            ),
            'parent_id' => new Property(
                type: Type::INTEGER,
                null: true,
                relation: self::class,
            ),
            'sort_key' => new Property(
                type: Type::STRING,
                in_array: false,
            ),
        ];
    }

    protected function initialize(): void
    {
        parent::initialize();
        self::saving([self::class, 'validateParent']);
        self::saving([self::class, 'determineSortKey']);
        self::saved([self::class, 'updateChildSortKeys']);
        self::deleting([self::class, 'blockDeletingParentAccounts']);
    }

    public static function validateParent(AbstractEvent $modelEvent): void
    {
        /** @var self $model */
        $model = $modelEvent->getModel();

        if (!$model->parent_id) {
            return;
        }

        $parent = $model->getParent();
        if (!$parent) {
            throw new ListenerException('No such G/L account: '.$model->parent_id, ['field' => 'parent_id']);
        }

        // check the max number of levels
        // look for circular dependencies
        $seen = [$model->id()];
        $codes = [$model->code];
        while ($parent) {
            if (in_array($parent->id(), $seen)) {
                $codes[] = $parent->code;
                throw new ListenerException('Detected circular dependency in account hierarchy: '.join(' -> ', $codes), ['field' => 'parent_id']);
            }

            if (count($seen) >= self::MAX_LEVELS) {
                throw new ListenerException('The maximum number of sub-account levels ('.self::MAX_LEVELS.') has been exceeded', ['field' => 'parent_id']);
            }

            $seen[] = $parent->id();
            $codes[] = $parent->code;
            $parent = $parent->getParent();
        }
    }

    public static function determineSortKey(AbstractEvent $modelEvent): void
    {
        /** @var self $model */
        $model = $modelEvent->getModel();

        $sortKey = [$model->name];
        $parent = $model->getParent();
        while ($parent) {
            $sortKey[] = $parent->name;
            $parent = $parent->getParent();
        }

        $model->sort_key = join(':', array_reverse($sortKey));
    }

    public static function updateChildSortKeys(AbstractEvent $modelEvent): void
    {
        /** @var self $model */
        $model = $modelEvent->getModel();

        $children = GlAccount::where('parent_id', $model->id())->all();
        foreach ($children as $glAccount) {
            $glAccount->updateSortKey();
        }
    }

    public static function blockDeletingParentAccounts(AbstractEvent $modelEvent): void
    {
        /** @var self $model */
        $model = $modelEvent->getModel();
        $children = GlAccount::where('parent_id', $model->id());
        if ($children->count() > 0) {
            throw new ListenerException('This account cannot be deleted because it has at least one sub-account. Please delete the sub-accounts first.', ['field' => 'parent_id']);
        }
    }

    public static function customizeBlankQuery(Query $query): Query
    {
        return $query->sort('sort_key ASC');
    }

    //
    // Validators
    //

    /**
     * Validates the account code.
     *
     * @param string $code
     */
    public static function validateCode($code): bool
    {
        // Allowed characters: a-z, A-Z, 0-9, -
        // Min length: 4
        return preg_match('/^[a-z0-9\-]{4,}$/i', $code) > 0;
    }

    //
    // Getters
    //

    public function getParent(): ?self
    {
        return $this->relation('parent_id');
    }

    //
    // Setters
    //

    public function updateSortKey(): bool
    {
        $this->sort_key = ''; // This will be rewritten

        return $this->save();
    }
}
