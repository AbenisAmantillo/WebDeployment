<?php

namespace App\Controller;

use App\Repository\PropertyRepository;
use App\Repository\FurnitureRepository;
use App\Repository\TransactionRepository;
use App\Repository\PaymentRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/data-records', name: 'app_data_records_')]
#[IsGranted('ROLE_ADMIN')]
class DataRecordsController extends AbstractController
{
    #[Route('/', name: 'index', methods: ['GET'])]
    public function index(
        PropertyRepository $propertyRepository,
        FurnitureRepository $furnitureRepository,
        TransactionRepository $transactionRepository,
        PaymentRepository $paymentRepository
    ): Response {
        return $this->render('data_records/index.html.twig', $this->loadDataRecordsPayload(
            $propertyRepository,
            $furnitureRepository,
            $transactionRepository,
            $paymentRepository
        ));
    }

    #[Route('/live-content', name: 'live_content', methods: ['GET'])]
    public function liveContent(
        PropertyRepository $propertyRepository,
        FurnitureRepository $furnitureRepository,
        TransactionRepository $transactionRepository,
        PaymentRepository $paymentRepository
    ): JsonResponse {
        $payload = $this->loadDataRecordsPayload(
            $propertyRepository,
            $furnitureRepository,
            $transactionRepository,
            $paymentRepository
        );

        $html = $this->renderView('data_records/_content.html.twig', $payload);

        return $this->json(['html' => $html]);
    }

    /**
     * @return array<string, mixed>
     */
    private function loadDataRecordsPayload(
        PropertyRepository $propertyRepository,
        FurnitureRepository $furnitureRepository,
        TransactionRepository $transactionRepository,
        PaymentRepository $paymentRepository
    ): array {
        $properties = $propertyRepository->findBy([], ['id' => 'DESC']);
        $furniture = $furnitureRepository->findBy([], ['id' => 'DESC']);
        $transactions = $transactionRepository->findBy([], ['id' => 'DESC']);
        $payments = $paymentRepository->findBy([], ['id' => 'DESC']);

        return [
            'properties' => $properties,
            'furniture' => $furniture,
            'transactions' => $transactions,
            'payments' => $payments,
            'total_properties' => count($properties),
            'total_furniture' => count($furniture),
            'total_transactions' => count($transactions),
            'total_payments' => count($payments),
        ];
    }
}

