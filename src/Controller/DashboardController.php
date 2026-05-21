<?php

namespace App\Controller;

use App\Repository\PropertyRepository;
use App\Repository\FurnitureRepository;
use App\Repository\PaymentRepository;
use App\Repository\TransactionRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
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
        // Calculate total revenue from all completed payments (money earned)
        $totalRevenue = $paymentRepository->getTotalRevenue();

        // ✅ Metrics based on real data
        $metrics = [
            'total_properties' => $propertyRepository->count([]),  // All properties
            'properties_pending' => $propertyRepository->count(['status' => 'PENDING']), // Pending
            'properties_sold' => $propertyRepository->count(['status' => 'SOLD']), // Sold
            'total_furnitures' => $furnitureRepository->count([]),
            'furnitures_sold' => $furnitureRepository->count(['status' => 'SOLD']),
            'total_revenue' => '₱' . number_format($totalRevenue, 2, '.', ','),
            'total_transactions' => $transactionRepository->count([]),
            'total_payments' => $paymentRepository->count([]),
        ];

        return $this->render('dashboard/index.html.twig', [
            'metrics' => $metrics,
            'company_name' => 'THE AMANTILLO PROPERTY CO.',
            'tagline' => 'Invest with confidence, Live with pride'
        ]);
    }
}