<?php

namespace App\Repository;

use App\Entity\Notification;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Notification>
 */
class NotificationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Notification::class);
    }

    /**
     * @return Notification[]
     */
    public function findAllOrderedByDate(): array
    {
        return $this->createQueryBuilder('n')
            ->leftJoin('n.recipient', 'r')
            ->addSelect('r')
            ->leftJoin('n.createdBy', 'c')
            ->addSelect('c')
            ->orderBy('n.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return Notification[]
     */
    public function findByRecipient(User $recipient): array
    {
        return $this->createQueryBuilder('n')
            ->andWhere('n.recipient = :recipient')
            ->setParameter('recipient', $recipient)
            ->orderBy('n.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
