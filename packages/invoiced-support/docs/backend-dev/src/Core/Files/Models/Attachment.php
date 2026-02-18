<?php

namespace App\Core\Files\Models;

use App\Core\Multitenant\Models\MultitenantModel;
use App\Core\Orm\Event\AbstractEvent;
use App\Core\Orm\Exception\DriverException;
use App\Core\Orm\Exception\ListenerException;
use App\Core\Orm\Exception\ModelException;
use App\Core\Orm\Iterator;
use App\Core\Orm\Model;
use App\Core\Orm\Property;
use App\Core\Orm\Query;
use App\Core\Orm\Traits\AutoTimestamps;
use App\Core\Orm\Type;
use App\Core\RestApi\Traits\ApiObjectTrait;
use App\Core\Utils\Enums\ObjectType;
use App\Sending\Email\Models\InboxEmail;

/**
 * Associates a file with a model.
 *
 * @property int    $id
 * @property string $parent_type
 * @property int    $parent_id
 * @property int    $file_id
 * @property string $location
 */
class Attachment extends MultitenantModel
{
    use ApiObjectTrait;
    use AutoTimestamps;

    const LOCATION_ATTACHMENT = 'attachment';
    const LOCATION_PDF = 'pdf';

    private ?Model $parent = null;

    protected static function getIDProperties(): array
    {
        return ['parent_type', 'parent_id', 'file_id'];
    }

    protected static function getProperties(): array
    {
        return [
            'parent_type' => new Property(
                mutable: Property::MUTABLE_CREATE_ONLY,
                required: true,
                validate: ['enum', 'choices' => ['credit_note', 'estimate', 'invoice', 'comment', 'payment', 'email', 'customer', 'remittance_advice']],
                in_array: false,
            ),
            'parent_id' => new Property(
                type: Type::INTEGER,
                mutable: Property::MUTABLE_CREATE_ONLY,
                required: true,
                in_array: false,
            ),
            'file_id' => new Property(
                type: Type::INTEGER,
                mutable: Property::MUTABLE_CREATE_ONLY,
                required: true,
                in_array: false,
                relation: File::class,
            ),
            'location' => new Property(
                required: true,
                default: self::LOCATION_ATTACHMENT,
            ),
        ];
    }

    protected function initialize(): void
    {
        self::creating([self::class, 'verifyParent']);
        self::creating([self::class, 'verifyFile']);

        parent::initialize();
    }

    public function create(array $data = []): bool
    {
        try {
            return parent::create($data);
        } catch (DriverException $e) {
            if (str_contains($e->getMessage(), 'Duplicate entry')) {
                return true;
            }

            throw $e;
        }
    }

    public function toArray(): array
    {
        $result = parent::toArray();
        $result['object'] = $this->object;
        $result['file'] = $this->file()->toArray();

        return $result;
    }

    public static function customizeBlankQuery(Query $query): Query
    {
        return $query->sort('created_at ASC');
    }

    /**
     * @return Query<static>
     */
    public static function queryForObject(Model $model, ?string $location): Query
    {
        if ($model instanceof InboxEmail) { // needed for BC
            $objectName = ObjectType::LegacyEmail->typeName();
        } else {
            $objectName = ObjectType::fromModel($model)->typeName();
        }

        $query = self::where('parent_type', $objectName)
            ->where('parent_id', $model)
            ->with('file_id');

        if ($location) {
            $query->where('location', $location);
        }

        return $query;
    }

    /**
     * @return Iterator<static>
     */
    public static function allForObject(Model $model, ?string $location = null, bool $desc = false): Iterator
    {
        $query = self::queryForObject($model, $location);

        if ($desc) {
            $query->sort('updated_at DESC');
        }

        return $query->all();
    }

    public static function countForObject(Model $model, string $location): int
    {
        $query = self::queryForObject($model, $location);

        return $query->count();
    }

    /**
     * Verifies the parent relationship when creating.
     */
    public static function verifyParent(AbstractEvent $event): void
    {
        /** @var self $model */
        $model = $event->getModel();

        try {
            $model->parent();
        } catch (ModelException $e) {
            throw new ListenerException($e->getMessage(), ['field', 'parent']);
        }
    }

    /**
     * Verifies the file relationship when creating.
     */
    public static function verifyFile(AbstractEvent $event): void
    {
        /** @var self $model */
        $model = $event->getModel();

        $fid = $model->file_id;
        if (!$fid) {
            throw new ListenerException('File missing', ['field' => 'file']);
        }

        $file = File::find($fid);
        if (!$file) {
            throw new ListenerException("No such file: $fid", ['field' => 'file']);
        }
    }

    //
    // Relationships
    //

    /**
     * @throws ModelException
     */
    public function parent(): Model
    {
        if (!$this->parent) {
            if ('email' == $this->parent_type) {
                $model = InboxEmail::class;
            } else {
                $model = ObjectType::fromTypeName($this->parent_type)->modelClass();
            }

            if ($parent = $model::find($this->parent_id)) {
                $this->parent = $parent;
            } else {
                throw new ModelException("No such parent of type '$this->parent_type' with ID '$this->parent_id'");
            }
        }

        return $this->parent;
    }

    /**
     * Sets parent.
     */
    public function setParent(Model $parent): void
    {
        if ($parent instanceof InboxEmail) {
            $this->parent_type = 'email';
        } else {
            $this->parent_type = ObjectType::fromModel($parent)->typeName();
        }
        $this->parent_id = (int) $parent->id();
        $this->parent = $parent;
    }

    /**
     * Gets the file.
     */
    public function file(): File
    {
        return $this->relation('file_id');
    }

    public function setFile(File $file): void
    {
        $this->file_id = (int) $file->id();
        $this->setRelation('file_id', $file);
    }
}
