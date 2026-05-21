<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Security\AppUserChecker;
use App\Security\JwtLoginResponseFactory;
use App\Service\Firebase\FirebaseAuthenticatedUser;
use App\Service\Firebase\FirebaseTokenMissingEmailException;
use App\Service\Firebase\FirebaseTokenVerifierInterface;
use App\Service\Firebase\InvalidFirebaseTokenException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;

final class FirebaseAuthController
{
    public function __construct(
        private readonly FirebaseTokenVerifierInterface $tokenVerifier,
        private readonly UserRepository $userRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly AppUserChecker $userChecker,
        private readonly JwtLoginResponseFactory $responseFactory,
    ) {
    }

    #[Route('/api/auth/firebase', name: 'api_auth_firebase', methods: ['POST'])]
    public function __invoke(Request $request): JsonResponse
    {
        $payload = json_decode($request->getContent(), true);
        $idToken = is_array($payload) ? ($payload['idToken'] ?? null) : null;

        if (!is_string($idToken) || trim($idToken) === '') {
            return new JsonResponse([
                'message' => 'Firebase idToken is required.',
            ], Response::HTTP_BAD_REQUEST);
        }

        try {
            $firebaseUser = $this->tokenVerifier->verify(trim($idToken));
        } catch (InvalidFirebaseTokenException) {
            return new JsonResponse([
                'message' => 'Invalid Firebase ID token.',
            ], Response::HTTP_UNAUTHORIZED);
        } catch (FirebaseTokenMissingEmailException) {
            return new JsonResponse([
                'message' => 'Firebase token does not include an email address.',
            ], Response::HTTP_BAD_REQUEST);
        }

        $user = $this->userRepository->findOneByEmail($firebaseUser->email);
        $shouldFlush = false;

        if (!$user instanceof User) {
            $user = $this->createFirebaseUser($firebaseUser);
            $this->entityManager->persist($user);
            $shouldFlush = true;
        } elseif (!$user->isVerified()) {
            $user->setIsVerified(true);
            $shouldFlush = true;
        }

        if ($shouldFlush) {
            $this->entityManager->flush();
        }

        if ($blockedResponse = $this->createBlockedLoginResponse($user)) {
            return $blockedResponse;
        }

        return $this->responseFactory->createAuthenticatedResponse($user);
    }

    private function createFirebaseUser(FirebaseAuthenticatedUser $firebaseUser): User
    {
        $user = new User();
        $user->setEmail($firebaseUser->email);
        $user->setUsername($this->generateUniqueUsername($firebaseUser));
        $user->setRoles(['ROLE_USER']);
        $user->setIsVerified(true);
        $user->setVerificationToken(null);

        $randomPassword = bin2hex(random_bytes(32));
        $user->setPassword($this->passwordHasher->hashPassword($user, $randomPassword));

        return $user;
    }

    private function generateUniqueUsername(FirebaseAuthenticatedUser $firebaseUser): string
    {
        $emailPrefix = strstr($firebaseUser->email, '@', true) ?: 'firebaseuser';
        $baseUsername = $firebaseUser->displayName ?: $emailPrefix;
        $baseUsername = preg_replace('/[^a-zA-Z0-9_]/', '', $baseUsername) ?: 'firebaseuser';
        $baseUsername = substr($baseUsername, 0, 170);
        $candidate = $baseUsername;
        $counter = 1;

        while ($this->userRepository->findOneBy(['username' => $candidate]) instanceof User) {
            $candidate = $baseUsername . $counter;
            $counter++;
        }

        return $candidate;
    }

    private function createBlockedLoginResponse(User $user): ?JsonResponse
    {
        try {
            $this->userChecker->checkPostAuth($user);
        } catch (CustomUserMessageAccountStatusException $exception) {
            if (!$user->isEnabled()) {
                return new JsonResponse([
                    'message' => $exception->getMessage(),
                ], Response::HTTP_UNAUTHORIZED);
            }

            return new JsonResponse([
                'message' => $exception->getMessage(),
            ], Response::HTTP_UNAUTHORIZED);
        }

        return null;
    }
}
