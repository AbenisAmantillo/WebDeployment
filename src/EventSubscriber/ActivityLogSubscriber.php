<?php

namespace App\EventSubscriber;

use App\Entity\User;
use App\Service\ActivityLogService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Security\Http\Event\InteractiveLoginEvent;
use Symfony\Component\Security\Http\Event\LogoutEvent;

class ActivityLogSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private ActivityLogService $activityLogService
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            InteractiveLoginEvent::class => 'onLogin',
            LogoutEvent::class => 'onLogout',
        ];
    }

    public function onLogin(InteractiveLoginEvent $event): void
    {
        $user = $event->getAuthenticationToken()->getUser();
        if ($user instanceof User) {
            // Log all users including admin
            $this->activityLogService->logActivity($user, 'User login');
        }
    }

    public function onLogout(LogoutEvent $event): void
    {
        $user = $event->getToken()?->getUser();
        if ($user instanceof User) {
            // Log all users including admin
            $this->activityLogService->logActivity($user, 'User logout');
        }
    }
}

