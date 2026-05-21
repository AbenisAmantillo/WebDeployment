<?php

namespace App\Repository;

use App\Entity\Transaction;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Transaction>
 */
class TransactionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Transaction::class);
    }

    /**
     * @return Transaction[]
     */
    public function findByCustomer(User $user): array
    {
        return $this->createQueryBuilder('t')
            ->leftJoin('t.payments', 'pay')
            ->addSelect('pay')
            ->where('t.customer = :user')
            ->setParameter('user', $user)
            ->orderBy('t.date', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function customerHasUnpaidTransaction(User $user): bool
    {
        foreach ($this->findByCustomer($user) as $transaction) {
            if (!$transaction->isFullyPaid()) {
                return true;
            }
        }

        return false;
    }

    public function findMostRecentOutstandingTransaction(User $user): ?Transaction
    {
        foreach ($this->findByCustomer($user) as $transaction) {
            if (!$transaction->isFullyPaid()) {
                return $transaction;
            }
        }

        return null;
    }

    //    /**
    //     * @return Transaction[] Returns an array of Transaction objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('t')
    //            ->andWhere('t.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('t.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?Transaction
    //    {
    //        return $this->createQueryBuilder('t')
    //            ->andWhere('t.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}

