<?php

namespace App\Security;

use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

final class AppUserChecker implements UserCheckerInterface
{
    public function checkPreAuth(UserInterface $user): void
    {
    }

    public function checkPostAuth(UserInterface $user, ?TokenInterface $token = null): void
    {
        if ($user instanceof InMemoryUser && !$user->isEnabled()) {
            $ex = new CustomUserMessageAccountStatusException('Account is disabled.');
            $ex->setUser($user);
            throw $ex;
        }

        if ($user instanceof User && !$user->isEnabled()) {
            $ex = new CustomUserMessageAccountStatusException('Your account is inactive.');
            $ex->setUser($user);
            throw $ex;
        }
    }
}
