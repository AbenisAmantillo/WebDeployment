<?php

namespace App\Repository;

use App\Entity\ActivityLog;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ActivityLog>
 */
class ActivityLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ActivityLog::class);
    }

    /**
     * Find all logs ordered by most recent first
     */
    public function findAllOrderedByDate(): array
    {
        return $this->createQueryBuilder('a')
            ->orderBy('a.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find logs for users with ROLE_USER or ROLE_STAFF only (exclude ROLE_ADMIN)
     */
    public function findUserAndStaffLogs(): array
    {
        $allLogs = $this->findAllOrderedByDate();
        
        // Filter logs where user has ROLE_USER or ROLE_STAFF, but not ROLE_ADMIN
        return array_filter($allLogs, function($log) {
            $roles = $log->getRoles();
            // Exclude if user has ROLE_ADMIN
            if (in_array('ROLE_ADMIN', $roles)) {
                return false;
            }
            // Include if user has ROLE_USER or ROLE_STAFF
            return in_array('ROLE_USER', $roles) || in_array('ROLE_STAFF', $roles);
        });
    }

    /**
     * Delete all logs for users with ROLE_ADMIN
     */
    public function deleteAdminLogs(): int
    {
        $allLogs = $this->findAll();
        $deletedCount = 0;
        
        foreach ($allLogs as $log) {
            $roles = $log->getRoles();
            if (in_array('ROLE_ADMIN', $roles)) {
                $this->getEntityManager()->remove($log);
                $deletedCount++;
            }
        }
        
        if ($deletedCount > 0) {
            $this->getEntityManager()->flush();
        }
        
        return $deletedCount;
    }

    /**
     * Delete all activity logs
     */
    public function deleteAll(): int
    {
        $allLogs = $this->findAll();
        $deletedCount = count($allLogs);
        
        foreach ($allLogs as $log) {
            $this->getEntityManager()->remove($log);
        }
        
        if ($deletedCount > 0) {
            $this->getEntityManager()->flush();
        }
        
        return $deletedCount;
    }
}

