<?php

namespace App\Security;

use App\Entity\User;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;

final class JwtLoginResponseFactory
{
    public function __construct(
        private readonly JWTTokenManagerInterface $jwtManager,
    ) {
    }

    public function createAuthenticatedResponse(User $user): JsonResponse
    {
        return new JsonResponse([
            'token' => $this->jwtManager->create($user),
            'user' => $this->createUserPayload($user),
        ]);
    }

    /**
     * @return array{id: int|null, username: string, email: string|null, roles: array<int, string>, verified: bool}
     */
    private function createUserPayload(User $user): array
    {
        return [
            'id' => $user->getId(),
            'username' => $user->getUserIdentifier(),
            'email' => $user->getEmail(),
            'roles' => $user->getRoles(),
            'verified' => $user->isVerified(),
        ];
    }
}
