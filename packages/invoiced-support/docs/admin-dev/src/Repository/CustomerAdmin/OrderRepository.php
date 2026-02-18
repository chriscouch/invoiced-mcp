<?php

namespace App\Repository\CustomerAdmin;

use App\Entity\CustomerAdmin\Order;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method Order|null find($id, $lockMode = null, $lockVersion = null)
 * @method Order|null findOneBy(array $criteria, array $orderBy = null)
 * @method Order[]    findAll()
 * @method Order[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class OrderRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Order::class);
    }

    /**
     * @return Order[]
     */
    public function getOpenNewAccountOrders(): array
    {
        return $this->createQueryBuilder('o')
            ->andWhere("o.status = 'open'")
            ->andWhere("(o.type = 'new_account' OR o.type = 'new_account_sow' OR o.type = 'new_account_reseller')")
            ->andWhere('o.newAccount IS NOT NULL')
            ->andWhere('o.start_date <= :start')
            ->setParameter('start', date('Y-m-d'))
            ->getQuery()
            ->getResult();
    }
}
