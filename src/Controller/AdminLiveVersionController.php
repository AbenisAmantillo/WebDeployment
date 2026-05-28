<?php

namespace App\Controller;

use App\Repository\ActivityLogRepository;
use App\Repository\FurnitureRepository;
use App\Repository\NotificationRepository;
use App\Repository\PaymentRepository;
use App\Repository\PropertyRepository;
use App\Repository\TransactionRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin')]
final class AdminLiveVersionController extends AbstractController
{
    #[Route('/live-version', name: 'app_admin_live_version', methods: ['GET'])]
    public function __invoke(
        PropertyRepository $propertyRepository,
        FurnitureRepository $furnitureRepository,
        TransactionRepository $transactionRepository,
        PaymentRepository $paymentRepository,
        NotificationRepository $notificationRepository,
        ActivityLogRepository $activityLogRepository
    ): JsonResponse {
        if (!$this->isGranted('ROLE_ADMIN') && !$this->isGranted('ROLE_STAFF')) {
            throw $this->createAccessDeniedException('Only admin or staff can access live admin updates.');
        }

        $latestNotificationAt = $notificationRepository->createQueryBuilder('n')
            ->select('MAX(n.createdAt)')
            ->getQuery()
            ->getSingleScalarResult();

        $latestActivityLogAt = $activityLogRepository->createQueryBuilder('a')
            ->select('MAX(a.createdAt)')
            ->getQuery()
            ->getSingleScalarResult();

        $versionPayload = [
            'propertyTotal' => $propertyRepository->count([]),
            'propertyPending' => $propertyRepository->count(['status' => 'PENDING']),
            'propertySold' => $propertyRepository->count(['status' => 'SOLD']),
            'furnitureTotal' => $furnitureRepository->count([]),
            'furnitureSold' => $furnitureRepository->count(['status' => 'SOLD']),
            'transactionTotal' => $transactionRepository->count([]),
            'transactionMaxId' => (int) ($transactionRepository->createQueryBuilder('t')->select('MAX(t.id)')->getQuery()->getSingleScalarResult() ?? 0),
            'paymentTotal' => $paymentRepository->count([]),
            'paymentMaxId' => (int) ($paymentRepository->createQueryBuilder('p')->select('MAX(p.id)')->getQuery()->getSingleScalarResult() ?? 0),
            'completedRevenue' => (string) $paymentRepository->getTotalRevenue(),
            'notificationTotal' => $notificationRepository->count([]),
            'latestNotificationAt' => $latestNotificationAt ? (new \DateTime((string) $latestNotificationAt))->format(\DateTimeInterface::ATOM) : null,
            'activityLogTotal' => $activityLogRepository->count([]),
            'latestActivityLogAt' => $latestActivityLogAt ? (new \DateTime((string) $latestActivityLogAt))->format(\DateTimeInterface::ATOM) : null,
        ];

        return $this->json([
            'version' => hash('sha256', json_encode($versionPayload, JSON_THROW_ON_ERROR)),
        ]);
    }
}
