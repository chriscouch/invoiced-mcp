<?php

namespace App\AccountsPayable\Models;

use App\Companies\Models\Member;
use App\Companies\Models\Role;
use App\Core\Multitenant\Models\MultitenantModel;
use Doctrine\DBAL\Connection;
use App\Core\Orm\Event\AbstractEvent;
use App\Core\Orm\Property;
use App\Core\Orm\Type;

/**
 * @property ApprovalWorkflowPath $approval_workflow_path
 * @property int                  $minimum_approvers
 * @property int                  $order
 * @property string[]|Role[]      $roles
 * @property int[]|Member[]       $members
 */
class ApprovalWorkflowStep extends MultitenantModel
{
    protected static function getProperties(): array
    {
        return [
            'approval_workflow_path' => new Property(
                required: true,
                belongs_to: ApprovalWorkflowPath::class,
            ),
            'minimum_approvers' => new Property(
                type: Type::INTEGER,
                required: true,
                default: 1,
            ),
            'order' => new Property(
                type: Type::INTEGER,
                required: true,
            ),
            'members' => new Property(
                default: [],
                foreign_key: 'member_id',
                local_key: 'approval_workflow_step_id',
                pivot_tablename: 'ApprovalWorkflowStepMembers',
                belongs_to_many: Member::class,
            ),
            'roles' => new Property(
                default: [],
                foreign_key: 'role_id',
                local_key: 'approval_workflow_step_id',
                pivot_tablename: 'ApprovalWorkflowStepRoles',
                belongs_to_many: Role::class,
            ),
        ];
    }

    public function isAllowed(Member $member): bool
    {
        foreach ($this->members as $mem) {
            $memberId = $mem instanceof Member ? $mem->id : $mem;
            if ($member->id === $memberId) {
                return true;
            }
        }

        $roleId = $member->role()->internal_id;
        foreach ($this->getRoleIds() as $role) {
            if ($roleId == $role) {
                return true;
            }
        }

        return false;
    }

    protected function initialize(): void
    {
        parent::initialize();
        self::saved([self::class, 'setRoles']);
        self::saved([self::class, 'setMembers']);
    }

    /**
     * @param int[] $exclude
     */
    private static function deleteManyToMany(Connection $connection, self $model, string $property, array $exclude = []): void
    {
        $properties = self::getProperties();
        /** @var string $table */
        $table = $properties[$property]->pivot_tablename;
        $lk = $properties[$property]->local_key;
        $fk = $properties[$property]->foreign_key;

        $qry = $connection->createQueryBuilder()
            ->delete($table)
            ->andWhere("$lk = :lid")
            ->setParameter('lid', $model->id());

        if ($exclude) {
            $qry->andWhere("$fk NOT IN (:exclusions)")
                ->setParameter('exclusions', $exclude, Connection::PARAM_STR_ARRAY);
        }
        $qry->executeStatement();
    }

    /**
     * @param int[] $toAdd
     */
    private static function insertManyToMany(Connection $connection, self $model, string $property, array $toAdd): void
    {
        $properties = self::getProperties();
        /** @var string $table */
        $table = $properties[$property]->pivot_tablename;
        $lk = $properties[$property]->local_key;
        $fk = $properties[$property]->foreign_key;
        foreach ($toAdd as $id) {
            $connection->insert($table, [
                $lk => $model->id(),
                $fk => $id,
            ]);
        }
    }

    public static function setRoles(AbstractEvent $event): void
    {
        /** @var self $model */
        $model = $event->getModel();
        /** @var Connection $connection */
        $connection = self::getDriver()->getConnection(null);
        if (empty($model->roles)) {
            self::deleteManyToMany($connection, $model, 'roles');

            return;
        }

        $rolesMap = Role::getRoleHashMap();

        $existing = $model->getRoleIds();
        $toAdd = array_map(fn ($item) => $item instanceof Role ? $item->internal_id : $rolesMap[$item], $model->roles);

        self::deleteManyToMany($connection, $model, 'roles', $toAdd);
        $toAdd = array_diff($toAdd, $existing);
        self::insertManyToMany($connection, $model, 'roles', $toAdd);
    }

    public static function setMembers(AbstractEvent $event): void
    {
        /** @var self $model */
        $model = $event->getModel();
        /** @var Connection $connection */
        $connection = self::getDriver()->getConnection(null);

        $toAdd = array_map(fn ($item) => $item instanceof Member ? $item->id : $item, $model->members);
        self::deleteManyToMany($connection, $model, 'members', $toAdd);
        if (empty($model->members)) {
            return;
        }

        $existing = $model->getMemberIds();
        $toAdd = array_diff($toAdd, $existing);
        self::insertManyToMany($connection, $model, 'members', $toAdd);
    }

    public function getNextStep(): ?self
    {
        return self::where('approval_workflow_path_id', $this->approval_workflow_path->id())
            ->where('order', $this->order + 1)
            ->oneOrNull();
    }

    public function getRoleIds(): array
    {
        return $this->getManyToManyRelationIds('roles');
    }

    public function getMemberIds(): array
    {
        return $this->getManyToManyRelationIds('members');
    }

    /**
     * @return int[]
     */
    public function getManyToManyRelationIds(string $property): array
    {
        $properties = self::getProperties();
        /** @var string $table */
        $table = $properties[$property]->pivot_tablename;
        $lk = $properties[$property]->local_key;
        $fk = $properties[$property]->foreign_key;
        $connection = self::getDriver()->getConnection(null);

        $ids = $connection->createQueryBuilder()
            ->select($fk)
            ->from($table)
            ->andWhere("$lk = :lid")
            ->setParameter('lid', $this->id())
            ->fetchAllAssociative();

        return array_map(fn ($item) => $item[$fk], $ids);
    }

    public function toArray(): array
    {
        $result = parent::toArray();

        $result['members'] = [];
        /** @var Member $member */
        foreach ($this->members as $member) {
            $user = $member->user();
            $result['members'][] = [
                'id' => $member->id,
                'user' => [
                    'first_name' => $user->first_name,
                    'last_name' => $user->last_name,
                    'email' => $user->email,
                ],
            ];
        }
        $roles = array_map(function ($item) {
            return $item instanceof Role ? [
                'id' => $item->id,
                'name' => $item->name,
            ] : $item;
        }, $this->roles);
        sort($roles);
        $result['roles'] = $roles;

        return $result;
    }
}
