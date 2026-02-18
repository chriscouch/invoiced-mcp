<?php

namespace App\AccountsPayable\Models;

use App\Core\Multitenant\Models\MultitenantModel;
use App\Core\Orm\Event\AbstractEvent;
use App\Core\Orm\Property;
use App\Core\Orm\Traits\AutoTimestamps;
use App\Core\Orm\Type;

/**
 * @property int                    $id
 * @property string                 $name
 * @property bool                   $default
 * @property bool                   $enabled
 * @property ApprovalWorkflowPath[] $paths
 */
class ApprovalWorkflow extends MultitenantModel
{
    use AutoTimestamps;

    protected static function getProperties(): array
    {
        return [
            'name' => new Property(
                type: Type::STRING,
                required: true,
                validate: [
                    ['string', 'min' => 1, 'max' => 255],
                    ['unique', 'column' => 'name'],
                ],
            ),
            'default' => new Property(
                type: Type::BOOLEAN,
                required: true,
                default: false,
            ),
            'enabled' => new Property(
                type: Type::BOOLEAN,
                required: true,
                default: true,
            ),
            'paths' => new Property(
                in_array: false,
                foreign_key: 'approval_workflow_id',
                has_many: ApprovalWorkflowPath::class,
            ),
        ];
    }

    public function determinePath(PayableDocument $doc): ?ApprovalWorkflowPath
    {
        foreach ($this->paths as $path) {
            if ($path->evaluateVendorDocument($doc)) {
                return $path;
            }
        }

        return null;
    }

    protected function initialize(): void
    {
        parent::initialize();

        self::saving([self::class, 'unsetDefault']);
    }

    public static function unsetDefault(AbstractEvent $event): void
    {
        /** @var self $model */
        $model = $event->getModel();
        if ($model->dirty('default') && $model->default) {
            self::getDriver()->getConnection(null)->createQueryBuilder()
                ->update('ApprovalWorkflows', 'a')
                ->set('a.default', 0)
                ->andWhere('tenant_id = '.$model->tenant_id)
                ->executeStatement();
        }
    }

    public function getVendorCreditsCountValue(): int
    {
        return VendorCredit::where('approval_workflow_id', $this->id)->count();
    }

    public function getBillsCountValue(): int
    {
        return Bill::where('approval_workflow_id', $this->id)->count();
    }

    public function getPathsListValue(): array
    {
        $paths = [];

        foreach ($this->paths as $path) {
            $paths[] = $path->toArray();
        }

        return $paths;
    }
}
