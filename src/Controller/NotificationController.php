<?php

namespace App\Controller;

use App\Entity\Notification;
use App\Entity\User;
use App\Form\NotificationType;
use App\Repository\NotificationRepository;
use App\Repository\UserRepository;
use App\Service\ActivityLogService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/notifications')]
#[IsGranted('ROLE_ADMIN')]
class NotificationController extends AbstractController
{
    #[Route('/', name: 'app_notification_index', methods: ['GET'])]
    public function index(NotificationRepository $notificationRepository): Response
    {
        return $this->render('notification/index.html.twig', [
            'notifications' => $this->loadNotificationListingData($notificationRepository),
        ]);
    }

    #[Route('/live-list', name: 'app_notification_live_list', methods: ['GET'])]
    public function liveList(NotificationRepository $notificationRepository): JsonResponse
    {
        $html = $this->renderView('notification/_list_content.html.twig', [
            'notifications' => $this->loadNotificationListingData($notificationRepository),
        ]);

        return $this->json(['html' => $html]);
    }

    #[Route('/new', name: 'app_notification_new', methods: ['GET', 'POST'])]
    public function new(
        Request $request,
        EntityManagerInterface $entityManager,
        ActivityLogService $activityLogService,
        UserRepository $userRepository,
    ): Response {
        $notification = new Notification();
        $preselectedClientId = $request->query->get('client');
        if (is_scalar($preselectedClientId) && preg_match('/^\d+$/', (string) $preselectedClientId)) {
            $client = $userRepository->find((int) $preselectedClientId);
            if ($client instanceof User) {
                $notification->setRecipient($client);
            }
        }

        $form = $this->createForm(NotificationType::class, $notification, [
            'recipients' => $userRepository->findNotificationRecipients(),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $admin = $this->getUser();
            if ($admin instanceof User) {
                $notification->setCreatedBy($admin);
            }

            $entityManager->persist($notification);
            $entityManager->flush();

            if ($admin instanceof User) {
                $recipientName = $notification->getRecipient()?->getUsername() ?? 'client';
                $activityLogService->logActivity(
                    $admin,
                    'Notification sent to ' . $recipientName
                );
            }

            $this->addFlash('success', 'Notification sent to the client.');
            return $this->redirectToRoute('app_notification_index');
        }

        return $this->render('notification/new.html.twig', [
            'form' => $form,
            'recipients' => $userRepository->findNotificationRecipients(),
        ]);
    }

    /**
     * @return array<int, Notification>
     */
    private function loadNotificationListingData(NotificationRepository $notificationRepository): array
    {
        return $notificationRepository->findAllOrderedByDate();
    }
}
