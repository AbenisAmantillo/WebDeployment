<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\AccountProfileType;
use App\Service\ActivityLogService;
use App\Service\UserProfileImageService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/account')]
#[IsGranted(new Expression('is_granted("ROLE_ADMIN") or is_granted("ROLE_STAFF")'))]
class AccountController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserProfileImageService $profileImageService,
        private readonly ActivityLogService $activityLogService,
    ) {
    }

    #[Route('', name: 'app_account_profile', methods: ['GET', 'POST'])]
    public function profile(Request $request, #[CurrentUser] User $user): Response
    {
        if (!$this->isStaffOrAdmin($user)) {
            throw $this->createAccessDeniedException('This page is only available to staff and administrator accounts.');
        }

        $form = $this->createForm(AccountProfileType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $uploaded = $form->get('profileImage')->getData();
            if ($uploaded) {
                try {
                    $this->profileImageService->replaceProfileImage($user, $uploaded);
                } catch (\InvalidArgumentException $e) {
                    $this->addFlash('error', $e->getMessage());

                    return $this->render('account/profile.html.twig', [
                        'form' => $form,
                        'user' => $user,
                    ]);
                } catch (FileException) {
                    $this->addFlash('error', 'Failed to upload profile photo. Please try again.');

                    return $this->render('account/profile.html.twig', [
                        'form' => $form,
                        'user' => $user,
                    ]);
                }
            } elseif ($form->get('removeProfileImage')->getData()) {
                $this->profileImageService->clearProfileImage($user);
            }

            $this->entityManager->flush();
            $this->activityLogService->logActivity($user, 'Updated account profile');

            $this->addFlash('success', 'Your account profile has been updated.');

            return $this->redirectToRoute('app_account_profile');
        }

        return $this->render('account/profile.html.twig', [
            'form' => $form,
            'user' => $user,
        ]);
    }

    private function isStaffOrAdmin(User $user): bool
    {
        $roles = $user->getRoles();

        return \in_array('ROLE_ADMIN', $roles, true) || \in_array('ROLE_STAFF', $roles, true);
    }
}
