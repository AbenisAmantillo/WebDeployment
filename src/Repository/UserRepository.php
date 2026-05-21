<?php

namespace App\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Users>
 */
class UserRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    public function findOneByEmail(string $email): ?User
    {
        return $this->createQueryBuilder('u')
            ->andWhere('LOWER(u.email) = :email')
            ->setParameter('email', strtolower($email))
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Clients and staff who can receive mobile notifications (excludes admins).
     *
     * @return User[]
     */
    public function findNotificationRecipients(): array
    {
        $users = $this->findBy([], ['username' => 'ASC']);

        return array_values(array_filter($users, static function (User $user): bool {
            $roles = $user->getRoles();
            if (in_array('ROLE_ADMIN', $roles, true)) {
                return false;
            }

            return in_array('ROLE_USER', $roles, true) || in_array('ROLE_STAFF', $roles, true);
        }));
    }

    

//    /**
//     * @return Users[] Returns an array of Users objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('u')
//            ->andWhere('u.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('u.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

//    public function findOneBySomeField($value): ?Users
//    {
//        return $this->createQueryBuilder('u')
//            ->andWhere('u.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}