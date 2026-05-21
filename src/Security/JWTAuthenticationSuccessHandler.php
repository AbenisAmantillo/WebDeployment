<?php

namespace App\Security;

use App\Entity\User;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationSuccessHandlerInterface;

class JWTAuthenticationSuccessHandler implements AuthenticationSuccessHandlerInterface
{
    public function __construct(
        private readonly JwtLoginResponseFactory $responseFactory,
    ) {}

    public function onAuthenticationSuccess(Request $request, TokenInterface $token): JsonResponse
    {
        /** @var User $user */
        $user = $token->getUser();

        return $this->responseFactory->createAuthenticatedResponse($user);
    }
}
