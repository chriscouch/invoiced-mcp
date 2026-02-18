<?php

namespace App\AccountsReceivable\Libs;

use App\AccountsReceivable\Models\Customer;
use Doctrine\DBAL\Connection;

class CustomerHierarchy
{
    const MAX_DEPTH = 5;

    public function __construct(private Connection $database)
    {
    }

    /**
     * Gets the IDs of the parent customer hierarchy
     * for a given customer. The array will be ordered
     * from highest-level to lowest-level.
     *
     * @return int[]
     */
    public function getParentIds(Customer $customer): array
    {
        // shortcut when there is no parent customer
        if (!$customer->parent_customer) {
            return [];
        }

        $sql = 'WITH RECURSIVE customer_path AS
   (
       SELECT id, parent_customer, CAST(id AS CHAR(50)) AS path, 0 AS level
       FROM Customers
       WHERE id='.$customer->id().'
       UNION ALL
       SELECT cp.id, c.parent_customer, CONCAT(cp.path, ",", CAST(c.id AS CHAR(50))) AS path, level + 1
       FROM customer_path AS cp JOIN Customers AS c ON cp.parent_customer = c.id
   )
SELECT path
FROM customer_path
WHERE id='.$customer->id().'
ORDER BY level DESC
LIMIT 1';
        $path = (string) $this->database->fetchOne($sql);

        $paths = explode(',', $path);
        // first result is input ID
        unset($paths[0]);
        // convert IDs to integers
        // due to recursion we must reverse sort
        $result = [];
        foreach (array_reverse($paths) as $value) {
            $result[] = (int) $value;
        }

        return $result;
    }

    /**
     * Gets the IDs of the sub-customer hierarchy
     * for a given customer. The array will be ordered
     * from highest-level to lowest-level.
     *
     * @return int[]
     */
    public function getSubCustomerIds(Customer $customer): array
    {
        return $this->getSubCustomerIdsByQuery([$customer->id]);
    }

    /**
     * @param int[] $ids
     *
     * @return int[]
     */
    public function getSubCustomerIdsByQuery(array $ids): array
    {
        if (0 === count($ids)) {
            return [];
        }

        $sql = 'WITH RECURSIVE customer_path (id) AS
   (
       SELECT id
       FROM Customers
       WHERE parent_customer IN ('.implode(',', $ids).')
       UNION ALL
       SELECT c.id
       FROM customer_path AS cp JOIN Customers AS c ON cp.id = c.parent_customer
   )
SELECT *
FROM customer_path
ORDER BY id ASC';

        return $this->database->fetchFirstColumn($sql);
    }

    /**
     * Bottom-up approach which calculates the depth of the given
     * customer from the root parent customer.
     * NOTE: 1-based indexing. I.e a root parent customer w/ no children
     * will return a depth of 1.
     */
    public function getDepthFromRoot(Customer $customer): int
    {
        $sql = 'with recursive depth(id, parent_id, depth) as
        (
            select id, parent_customer, 1 from Customers where id = ?
            union all
            select Customers.id, Customers.parent_customer, depth + 1 from Customers
            join depth on depth.parent_id = Customers.id
        )
        select MAX(depth) as "depth" from depth';

        $rows = $this->database->fetchAllAssociative($sql, [$customer->id()]);

        // convert IDs to integers
        return max((int) $rows[0]['depth'], 1);
    }

    /**
     * Top-down approach which calculates the depth of the customer hierarchy
     * starting at the given customer.
     * NOTE: 1-based indexing. I.e a root parent customer w/ no children
     * will return a depth of 1.
     */
    public function getMaxDepthFromCustomer(Customer $customer): int
    {
        $sql = 'with recursive depth(id, parent_id, depth) as
        (
            select id, parent_customer, 1 from Customers where id = ?
            union all
            select Customers.id, Customers.parent_customer, depth + 1 from Customers
            join depth on depth.id = Customers.parent_customer
        )
        select MAX(depth) as "depth" from depth';

        $rows = $this->database->fetchAllAssociative($sql, [$customer->id()]);

        // convert IDs to integers
        return max((int) $rows[0]['depth'], 1);
    }
}
