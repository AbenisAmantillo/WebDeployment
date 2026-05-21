<?php

namespace App\Controller;

use App\Entity\User;
use App\Service\ActivityLogService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

class ApiLogoutController extends AbstractController
{
    /**
     * JWT clients call this before clearing the token locally.
     * Web logout uses the session firewall and ActivityLogSubscriber::onLogout.
     */
    #[Route('/api/logout', name: 'api_logout', methods: ['POST'])]
    public function logout(
        #[CurrentUser] ?User $user,
        ActivityLogService $activityLogService,
    ): JsonResponse {
        if ($user === null) {
            return $this->json(['message' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        $activityLogService->logActivity($user, 'User logout');

        return $this->json(['success' => true]);
    }
}
