<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\TransactionRepository;
use App\Security\ClientPortalAccessChecker;
use App\Service\UserProfileImageService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

class ApiMeController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserProfileImageService $profileImageService,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly TransactionRepository $transactionRepository,
        private readonly ClientPortalAccessChecker $clientPortalAccess,
    ) {
    }

    #[Route('/api/me', name: 'api_me', methods: ['GET'])]
    public function me(#[CurrentUser] ?User $user): JsonResponse
    {
        if ($user === null) {
            return $this->json(['message' => 'Not authenticated'], Response::HTTP_UNAUTHORIZED);
        }

        return $this->json($this->serializeUser($user));
    }

    #[Route('/api/me', name: 'api_me_patch', methods: ['PATCH'])]
    public function updateMe(Request $request, #[CurrentUser] ?User $user): JsonResponse
    {
        if ($user === null) {
            return $this->json(['message' => 'Not authenticated'], Response::HTTP_UNAUTHORIZED);
        }

        $data = json_decode($request->getContent(), true);
        if (!\is_array($data)) {
            return $this->json(['message' => 'Invalid JSON body'], Response::HTTP_BAD_REQUEST);
        }

        if (\array_key_exists('email', $data)) {
            return $this->json(['message' => 'Email cannot be changed'], Response::HTTP_BAD_REQUEST);
        }

        if (\array_key_exists('username', $data)) {
            $username = trim((string) $data['username']);
            if (\strlen($username) < 3) {
                return $this->json(['message' => 'Username must be at least 3 characters'], Response::HTTP_BAD_REQUEST);
            }
            $user->setUsername($username);
            $this->entityManager->flush();
        }

        return $this->json($this->serializeUser($user));
    }

    #[Route('/api/me/profile-image', name: 'api_me_profile_image', methods: ['POST'])]
    public function uploadProfileImage(Request $request, #[CurrentUser] ?User $user): JsonResponse
    {
        if ($user === null) {
            return $this->json(['message' => 'Not authenticated'], Response::HTTP_UNAUTHORIZED);
        }

        /** @var UploadedFile|null $file */
        $file = $request->files->get('file');
        if (!$file instanceof UploadedFile) {
            return $this->json(['message' => 'No image file provided'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $this->profileImageService->replaceProfileImage($user, $file);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['message' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        } catch (FileException) {
            return $this->json(['message' => 'Failed to upload image'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $this->entityManager->flush();

        return $this->json($this->serializeUser($user));
    }

    #[Route('/api/me/change-password', name: 'api_me_change_password', methods: ['POST'])]
    public function changePassword(Request $request, #[CurrentUser] ?User $user): JsonResponse
    {
        if ($user === null) {
            return $this->json(['message' => 'Not authenticated'], Response::HTTP_UNAUTHORIZED);
        }

        $data = json_decode($request->getContent(), true);
        if (!\is_array($data)) {
            return $this->json(['message' => 'Invalid JSON body'], Response::HTTP_BAD_REQUEST);
        }

        $current = (string) ($data['currentPassword'] ?? '');
        $new = (string) ($data['newPassword'] ?? '');

        if ($current === '' || $new === '') {
            return $this->json(['message' => 'Current password and new password are required'], Response::HTTP_BAD_REQUEST);
        }

        if (\strlen($new) < 6) {
            return $this->json(['message' => 'New password must be at least 6 characters'], Response::HTTP_BAD_REQUEST);
        }

        if (!$this->passwordHasher->isPasswordValid($user, $current)) {
            return $this->json(['message' => 'Current password is incorrect'], Response::HTTP_BAD_REQUEST);
        }

        $user->setPassword($this->passwordHasher->hashPassword($user, $new));
        $this->entityManager->flush();

        return $this->json(['message' => 'Password updated successfully']);
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeUser(User $user): array
    {
        $payload = [
            'id' => $user->getId(),
            'username' => $user->getUsername(),
            'email' => $user->getEmail(),
            'roles' => $user->getRoles(),
            'verified' => $user->isVerified(),
            'profileImageFileName' => $user->getProfileImageFileName(),
        ];

        if ($this->clientPortalAccess->isCustomer()) {
            $payload['userCanCreate'] = !$this->transactionRepository->customerHasUnpaidTransaction($user);
        }

        return $payload;
    }
}
