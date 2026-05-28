<?php

namespace App\Controller;

use App\Repository\PropertyRepository;
use App\Repository\FurnitureRepository;
use App\Repository\PaymentRepository;
use App\Repository\TransactionRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class DashboardController extends AbstractController
{
    #[Route('/dashboard', name: 'app_dashboard')]
    public function index(
        PropertyRepository $propertyRepository,
        FurnitureRepository $furnitureRepository,
        PaymentRepository $paymentRepository,
        TransactionRepository $transactionRepository
    ): Response {
        $metrics = $this->buildDashboardMetrics(
            $propertyRepository,
            $furnitureRepository,
            $paymentRepository,
            $transactionRepository
        );

        return $this->render('dashboard/index.html.twig', [
            'metrics' => $metrics,
            'company_name' => 'THE AMANTILLO PROPERTY CO.',
            'tagline' => 'Invest with confidence, Live with pride'
        ]);
    }

    #[Route('/dashboard/live-metrics', name: 'app_dashboard_live_metrics', methods: ['GET'])]
    public function liveMetrics(
        PropertyRepository $propertyRepository,
        FurnitureRepository $furnitureRepository,
        PaymentRepository $paymentRepository,
        TransactionRepository $transactionRepository
    ): JsonResponse {
        if (!$this->isGranted('ROLE_ADMIN') && !$this->isGranted('ROLE_STAFF')) {
            throw $this->createAccessDeniedException('Only admin or staff can access live metrics.');
        }

        return $this->json([
            'metrics' => $this->buildDashboardMetrics(
                $propertyRepository,
                $furnitureRepository,
                $paymentRepository,
                $transactionRepository
            ),
        ]);
    }

    /**
     * @return array<string, string|int>
     */
    private function buildDashboardMetrics(
        PropertyRepository $propertyRepository,
        FurnitureRepository $furnitureRepository,
        PaymentRepository $paymentRepository,
        TransactionRepository $transactionRepository
    ): array {
        $totalRevenue = $paymentRepository->getTotalRevenue();

        return [
            'total_properties' => $propertyRepository->count([]),
            'properties_pending' => $propertyRepository->count(['status' => 'PENDING']),
            'properties_sold' => $propertyRepository->count(['status' => 'SOLD']),
            'total_furnitures' => $furnitureRepository->count([]),
            'furnitures_sold' => $furnitureRepository->count(['status' => 'SOLD']),
            'total_revenue' => '₱' . number_format($totalRevenue, 2, '.', ','),
            'total_transactions' => $transactionRepository->count([]),
            'total_payments' => $paymentRepository->count([]),
        ];
    }
}