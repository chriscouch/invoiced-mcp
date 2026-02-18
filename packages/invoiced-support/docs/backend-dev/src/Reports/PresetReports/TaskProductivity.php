<?php

namespace App\Reports\PresetReports;

use App\Reports\ValueObjects\NestedTableGroup;
use App\Reports\ValueObjects\Section;
use Carbon\CarbonImmutable;

class TaskProductivity extends AbstractReport
{
    public static function getId(): string
    {
        return 'task_productivity';
    }

    private string $locale;

    protected function getName(): string
    {
        return 'Task Productivity';
    }

    protected function build(): void
    {
        $this->locale = $this->company->getLocale();
        /** @var CarbonImmutable $start */
        $start = CarbonImmutable::createFromTimestamp($this->start)->locale($this->locale);
        /** @var CarbonImmutable $end */
        $end = CarbonImmutable::createFromTimestamp($this->end)->locale($this->locale);

        $query = $this->database->prepare('SELECT first_name,
                   last_name,
                   action,
                   SUM(task_productivity.assigned)                             AS assigned,
                   SUM(task_productivity.completed)                            AS completed,
                   ROUND((AVG(task_productivity.seconds_to_complete) / 86400)) AS avg_days
            FROM (SELECT tenant_id                                                            AS tenant_id,
                         action                                                               AS action,
                         user_id                                                              AS user_id,
                         created_at                                                           AS created_at,
                         completed_date                                                       AS completed_date,
                         1                                                                    AS assigned,
                         IF(user_id = completed_by_user_id, 1, 0)                             AS completed,
                         IF((user_id = completed_by_user_id),
                            GREATEST((completed_date - UNIX_TIMESTAMP(created_at)), 0), null) AS seconds_to_complete
                  FROM Tasks
                  WHERE tenant_id = :tenant
                    AND created_at BETWEEN :start AND :end
                    AND user_id IS NOT NULL
                  UNION ALL
                  SELECT tenant_id            AS tenant_id,
                         action               AS action,
                         completed_by_user_id AS user_id,
                         created_at           AS created_at,
                         null                 AS completed_date,
                         0                    AS assigned,
                         1                    AS completed,
                         null                 AS seconds_to_complete
                  FROM Tasks
                  WHERE tenant_id = :tenant
                    AND created_at BETWEEN :start AND :end
                    AND completed_by_user_id IS NOT NULL
                    AND completed_by_user_id <> user_id) task_productivity
            JOIN Users ON id = user_id
            JOIN Members ON task_productivity.user_id = Members.user_id AND Members.tenant_id = :tenant
            GROUP BY task_productivity.user_id, task_productivity.action
            ORDER BY first_name, last_name, action
            LIMIT 10000
        ');
        $query->bindValue('tenant', $this->company->id);
        $query->bindValue('start', $start->format('Y-m-d H:i:s'));
        $query->bindValue('end', $end->format('Y-m-d H:i:s'));

        $result = $query->executeQuery()->fetchAllAssociative();

        $name = null;
        $section = null;
        $group = null;
        $assigned = 0;
        $completed = 0;
        foreach ($result as $item) {
            $newName = $item['first_name'].' '.$item['last_name'];
            if ($name !== $newName) {
                $this->finalizeSection($section, $group, $assigned, $completed);
                $name = $newName;
                $assigned = 0;
                $completed = 0;
                $section = new Section($name);
                $group = new NestedTableGroup([
                    [
                        'name' => 'Task Type',
                        'type' => 'string',
                    ], [
                        'name' => '# Assigned',
                        'type' => 'integer',
                    ], [
                        'name' => '# Complete',
                        'type' => 'integer',
                    ], [
                        'name' => 'Avg. Time to Complete (days)',
                        'type' => 'integer',
                    ],
                ]);
            }
            $group?->addRow([
                $item['action'],
                $item['assigned'],
                $item['completed'],
                $item['avg_days'] ?: 'N\A',
            ]);

            $assigned += $item['assigned'];
            $completed += $item['completed'];
        }

        $this->finalizeSection($section, $group, $assigned, $completed);
    }

    private function finalizeSection(?Section $section, ?NestedTableGroup $group, int $assigned, int $completed): void
    {
        if (null !== $section && null !== $group) {
            $group->setFooter([
                'Total',
                $assigned,
                $completed,
                '',
            ]);
            $section->addGroup($group);
            $this->report->addSection($section);
        }
    }
}
