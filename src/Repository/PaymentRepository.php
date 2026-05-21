<?php

namespace App\Repository;

use App\Entity\Payment;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Payment>
 */
class PaymentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Payment::class);
    }

    /**
     * @return Payment[]
     */
    public function findStaffPaymentRecords(): array
    {
        return $this->createQueryBuilder('p')
            ->leftJoin('p.customer', 'c')
            ->addSelect('c')
            ->leftJoin('p.transaction', 't')
            ->addSelect('t')
            ->andWhere('LOWER(p.status) IN (:statuses)')
            ->setParameter('statuses', ['completed', 'submitted'])
            ->orderBy('p.date', 'DESC')
            ->addOrderBy('p.id', 'DESC')
            ->getQuery()
            ->getResult();
    }

    //    /**
    //     * @return Payment[] Returns an array of Payment objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('p')
    //            ->andWhere('p.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('p.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    /**
     * Calculate total revenue from all completed payments (total money earned)
     * 
     * @return float Total revenue amount
     */
    public function getTotalRevenue(): float
    {
        $result = $this->createQueryBuilder('p')
            ->select('COALESCE(SUM(p.amount), 0) as total')
            ->where('LOWER(p.status) = :status')
            ->setParameter('status', 'completed')
            ->getQuery()
            ->getSingleScalarResult();

        return (float) ($result ?? 0);
    }

    //    public function findOneBySomeField($value): ?Payment
    //    {
    //        return $this->createQueryBuilder('p')
    //            ->andWhere('p.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}

