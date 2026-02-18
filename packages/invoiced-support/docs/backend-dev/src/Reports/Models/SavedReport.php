<?php

namespace App\Reports\Models;

use App\Companies\Models\Member;
use App\Core\Multitenant\Models\MultitenantModel;
use App\Reports\Exceptions\ReportException;
use App\Reports\ReportBuilder\DefinitionDeserializer;
use App\Core\Orm\ACLModelRequester;
use App\Core\Orm\Event\AbstractEvent;
use App\Core\Orm\Event\ModelCreating;
use App\Core\Orm\Exception\ListenerException;
use App\Core\Orm\Property;
use App\Core\Orm\Traits\AutoTimestamps;
use App\Core\Orm\Type;

/**
 * @property int    $id
 * @property string $name
 * @property string $definition
 * @property bool   $private
 * @property Member $creator
 * @property int    $creator_id
 */
class SavedReport extends MultitenantModel
{
    use AutoTimestamps;

    private const COMPANY_LIMIT = 100;

    protected static function getProperties(): array
    {
        return [
            'name' => new Property(
                required: true,
                validate: ['unique', 'column' => 'name'],
            ),
            'definition' => new Property(
                required: true,
                validate: ['callable', 'fn' => [self::class, 'validateDefinition']],
            ),
            'private' => new Property(
                type: Type::BOOLEAN,
                default: true,
            ),
            'creator' => new Property(
                required: true,
                belongs_to: Member::class,
            ),
            'creator_id' => new Property(
                type: Type::INTEGER,
                in_array: false,
            ),
        ];
    }

    protected function initialize(): void
    {
        parent::initialize();
        self::creating([self::class, 'setCreator']);
        self::creating([self::class, 'companyLimit']);
        self::updating([self::class, 'companyLimitUpdate']);
    }

    public static function setCreator(ModelCreating $event): void
    {
        /** @var SavedReport $report */
        $report = $event->getModel();
        $requester = ACLModelRequester::get();
        if ($requester instanceof Member) {
            $report->creator = $requester;
        }
    }

    public static function companyLimit(): void
    {
        if (self::where('private', false)->count() > self::COMPANY_LIMIT) {
            throw new ListenerException('You can not create more than '.self::COMPANY_LIMIT.' saved reports per company.');
        }
    }

    public static function companyLimitUpdate(AbstractEvent $event): void
    {
        /** @var self $model */
        $model = $event->getModel();
        if ($model->dirty('private', true) && !$model->private) {
            self::companyLimit();
        }
    }

    public static function validateDefinition(mixed $input, array $options, SavedReport $report): bool
    {
        if (!is_string($input)) {
            $report->getErrors()->add('Definition must be a string', ['field' => 'definition']);

            return false;
        }

        try {
            $requester = ACLModelRequester::get();
            $member = $requester instanceof Member ? $requester : null;

            DefinitionDeserializer::deserialize($input, $report->tenant(), $member);

            return true;
        } catch (ReportException $e) {
            $report->getErrors()->add($e->getMessage(), ['field' => 'definition']);

            return false;
        }
    }

    public function toArray(): array
    {
        $result = parent::toArray();

        if ($creator = $this->creator) { /* @phpstan-ignore-line */
            $result['creator'] = $creator->user;
        } else {
            $result['creator'] = null;
        }

        return $result;
    }
}
