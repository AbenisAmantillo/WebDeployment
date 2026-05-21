<?php

namespace App\Service;

use App\Entity\ActivityLog;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

class ActivityLogService
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {
    }

    public function logActivity(User $user, string $action): void
    {
        try {
            $log = new ActivityLog();
            $log->setUsername($user->getUsername());
            $log->setRoles($user->getRoles());
            $log->setAction($action);
            $log->setCreatedAt(new \DateTime());

            $this->entityManager->persist($log);
            // Flush immediately to ensure log is saved even if main transaction fails
            $this->entityManager->flush();
        } catch (\Exception $e) {
            // Silently fail logging to not break main operations
            // In production, you might want to log this error
        }
    }
}

