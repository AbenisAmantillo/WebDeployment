<?php

namespace App\Repository;

use App\Entity\TransactionFurniture;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TransactionFurniture>
 */
class TransactionFurnitureRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TransactionFurniture::class);
    }
}

