<?php

namespace App\Security;

use App\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

final class ClientPortalAccessChecker
{
    public function __construct(
        private readonly Security $security,
    ) {
    }

    public function isStaffOrAdmin(): bool
    {
        return $this->security->isGranted('ROLE_ADMIN') || $this->security->isGranted('ROLE_STAFF');
    }

    public function isCustomer(): bool
    {
        return $this->security->isGranted('ROLE_USER') && !$this->isStaffOrAdmin();
    }

    public function getCurrentUser(): ?User
    {
        $user = $this->security->getUser();

        return $user instanceof User ? $user : null;
    }

    public function assertCustomerPortalAccess(): User
    {
        $user = $this->getCurrentUser();
        if ($user === null) {
            throw new AccessDeniedException('Authentication required.');
        }

        if ($this->isStaffOrAdmin()) {
            throw new AccessDeniedException('Staff and administrators must use the admin interface, not the client portal.');
        }

        if (!$this->security->isGranted('ROLE_USER')) {
            throw new AccessDeniedException('Client access requires ROLE_USER.');
        }

        return $user;
    }
}
