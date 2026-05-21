<?php

namespace App\Repository;

use App\Entity\Furniture;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Furniture>
 */
class FurnitureRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Furniture::class);
    }

    /**
     * @return Furniture[]
     */
    public function findAvailableOrderedByIdDesc(int $limit): array
    {
        return $this->createQueryBuilder('f')
            ->where('LOWER(f.status) = :available')
            ->andWhere('f.stock IS NULL OR f.stock > 0')
            ->setParameter('available', 'available')
            ->orderBy('f.id', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

//    /**
//     * @return Furniture[] Returns an array of Furniture objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('f')
//            ->andWhere('f.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('f.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

//    public function findOneBySomeField($value): ?Furniture
//    {
//        return $this->createQueryBuilder('f')
//            ->andWhere('f.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}