<?php

namespace App\Controller;

use App\Entity\Furniture;
use App\Entity\Payment;
use App\Entity\Property;
use App\Entity\Transaction;
use App\Entity\User;
use App\Repository\FurnitureRepository;
use App\Repository\NotificationRepository;
use App\Repository\PropertyRepository;
use App\Repository\TransactionRepository;
use App\Security\ClientPortalAccessChecker;
use App\Service\ClientCheckoutService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
class ClientApiController extends AbstractController
{
    public function __construct(
        private readonly ClientPortalAccessChecker $clientPortalAccess,
        private readonly PropertyRepository $propertyRepository,
        private readonly FurnitureRepository $furnitureRepository,
        private readonly TransactionRepository $transactionRepository,
        private readonly NotificationRepository $notificationRepository,
        private readonly ClientCheckoutService $checkoutService,
    ) {
    }

    #[Route('/api/client/dashboard', name: 'api_client_dashboard', methods: ['GET'])]
    public function dashboard(): JsonResponse
    {
        $user = $this->requireCustomer();

        $properties = $this->propertyRepository->findAvailableOrderedByIdDesc(6);
        $featuredProperties = array_slice($properties, 0, 4);
        $moreProperties = array_slice($properties, 4, 2);
        $furniture = $this->furnitureRepository->findAvailableOrderedByIdDesc(6);
        $userCanCreate = !$this->transactionRepository->customerHasUnpaidTransaction($user);
        $activeTransaction = $this->transactionRepository->findMostRecentOutstandingTransaction($user);
        $notifications = $this->notificationRepository->findByRecipient($user);

        return $this->json([
            'featuredProperties' => array_map([$this, 'serializeProperty'], $featuredProperties),
            'moreProperties' => array_map([$this, 'serializeProperty'], $moreProperties),
            'furniture' => array_map([$this, 'serializeFurniture'], $furniture),
            'userCanCreate' => $userCanCreate,
            'activeTransactionSummary' => $activeTransaction ? $this->serializeActiveTransactionSummary($activeTransaction) : null,
            'unreadNotificationsCount' => count($notifications),
        ]);
    }

    #[Route('/api/checkout', name: 'api_checkout', methods: ['POST'])]
    #[Route('/api/client/checkout', name: 'api_client_checkout', methods: ['POST'])]
    public function checkout(Request $request): JsonResponse
    {
        $user = $this->requireCustomer();

        $data = json_decode($request->getContent(), true);
        if (!is_array($data)) {
            return $this->json(['message' => 'Invalid JSON body'], Response::HTTP_BAD_REQUEST);
        }

        $propertyId = $this->extractId($data['propertyId'] ?? $data['property'] ?? null);
        if ($propertyId === null) {
            return $this->json(['message' => 'propertyId is required'], Response::HTTP_BAD_REQUEST);
        }

        $furnitureLines = $this->extractFurnitureLines($data);
        $downpayment = $this->firstPresent($data, ['downpayment', 'clientDownpaymentAmount', 'client_downpayment_amount']);
        $paymentPlanMonths = $this->firstPresent($data, ['paymentPlanMonths', 'clientPaymentPlanMonths', 'payment_plan_months']);
        $paymentMethod = (string) ($this->firstPresent($data, ['paymentMethod', 'clientPaymentMethod', 'payment_method']) ?? '');

        if (!is_numeric((string) $downpayment) || !is_numeric((string) $paymentPlanMonths)) {
            return $this->json(['message' => 'downpayment and paymentPlanMonths are required'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $transaction = $this->checkoutService->checkout(
                $user,
                $propertyId,
                $furnitureLines,
                (float) $downpayment,
                (int) $paymentPlanMonths,
                $paymentMethod,
            );
        } catch (\Symfony\Component\HttpKernel\Exception\HttpExceptionInterface $exception) {
            return $this->json(['message' => $exception->getMessage()], $exception->getStatusCode());
        }

        return $this->json([
            'transactionId' => $transaction->getId(),
            'transaction' => $this->serializeTransaction($transaction),
        ], Response::HTTP_CREATED);
    }

    #[Route('/api/client/payments/{id}/complete', name: 'api_client_payment_complete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function completeClientPayment(int $id): JsonResponse
    {
        return $this->completePayment($id);
    }

    #[Route('/api/payments/{id}/complete', name: 'api_payment_complete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function completePayment(int $id): JsonResponse
    {
        $this->requireCustomer();

        return $this->json([
            'message' => 'Payments must be confirmed by staff.',
        ], Response::HTTP_FORBIDDEN);
    }

    private function requireCustomer(): User
    {
        try {
            return $this->clientPortalAccess->assertCustomerPortalAccess();
        } catch (\Symfony\Component\Security\Core\Exception\AccessDeniedException $exception) {
            throw new AccessDeniedHttpException($exception->getMessage());
        }
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return list<array{furnitureId: int, quantity: int}>
     */
    private function extractFurnitureLines(array $data): array
    {
        $rawLines = $data['furnitureLines'] ?? $data['furniture_lines'] ?? $data['items'] ?? [];
        if (!is_array($rawLines)) {
            return [];
        }

        $lines = [];
        foreach ($rawLines as $rawLine) {
            if (!is_array($rawLine)) {
                $furnitureId = $this->extractId($rawLine);
                if ($furnitureId !== null) {
                    $lines[] = ['furnitureId' => $furnitureId, 'quantity' => 1];
                }

                continue;
            }

            $furnitureId = $this->extractId($rawLine['furnitureId'] ?? $rawLine['furniture'] ?? $rawLine['id'] ?? null);
            $quantity = $rawLine['quantity'] ?? 1;
            if ($furnitureId === null || !is_numeric((string) $quantity)) {
                continue;
            }

            $lines[] = ['furnitureId' => $furnitureId, 'quantity' => (int) $quantity];
        }

        return $lines;
    }

    /**
     * @param array<string, mixed> $data
     * @param list<string> $keys
     */
    private function firstPresent(array $data, array $keys): mixed
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $data)) {
                return $data[$key];
            }
        }

        return null;
    }

    private function extractId(mixed $value): ?int
    {
        if (is_array($value)) {
            return $this->extractId($value['id'] ?? $value['@id'] ?? null);
        }

        if (is_int($value)) {
            return $value > 0 ? $value : null;
        }

        if (!is_string($value) || trim($value) === '') {
            return null;
        }

        if (ctype_digit($value)) {
            $id = (int) $value;

            return $id > 0 ? $id : null;
        }

        if (preg_match('~/(\d+)$~', $value, $matches) === 1) {
            return (int) $matches[1];
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeProperty(Property $property): array
    {
        return [
            'id' => $property->getId(),
            'title' => $property->getTitle(),
            'status' => $property->getStatus(),
            'price' => $property->getPrice(),
            'address' => $property->getAddress(),
            'imageFileName' => $property->getImageFileName(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeFurniture(Furniture $furniture): array
    {
        return [
            'id' => $furniture->getId(),
            'name' => $furniture->getName(),
            'status' => $furniture->getStatus(),
            'price' => $furniture->getPrice(),
            'stock' => $furniture->getStock(),
            'image' => $furniture->getImage(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeTransaction(Transaction $transaction): array
    {
        return [
            'id' => $transaction->getId(),
            'price' => $transaction->getPrice(),
            'date' => $transaction->getDate()?->format(\DateTimeInterface::ATOM),
            'purchaseType' => $transaction->getPurchaseType(),
            'isFullyPaid' => $transaction->isFullyPaid(),
            'paidAmount' => $transaction->getPaidAmount(),
            'outstandingBalance' => $transaction->getOutstandingBalance(),
            'property' => $transaction->getProperty() ? $this->serializeProperty($transaction->getProperty()) : null,
            'transactionFurniture' => array_map(
                static fn ($line) => [
                    'id' => $line->getId(),
                    'quantity' => $line->getQuantity(),
                    'furniture' => $line->getFurniture() ? [
                        'id' => $line->getFurniture()->getId(),
                        'name' => $line->getFurniture()->getName(),
                        'price' => $line->getFurniture()->getPrice(),
                        'image' => $line->getFurniture()->getImage(),
                    ] : null,
                ],
                $transaction->getTransactionFurniture()->toArray()
            ),
            'payments' => array_map([$this, 'serializePayment'], $transaction->getPayments()->toArray()),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializePayment(Payment $payment): array
    {
        return [
            'id' => $payment->getId(),
            'amount' => $payment->getAmount(),
            'paymentMethod' => $payment->getPaymentMethod(),
            'status' => $payment->getStatus(),
            'date' => $payment->getDate()?->format(\DateTimeInterface::ATOM),
            'transaction' => $payment->getTransaction()?->getId(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeActiveTransactionSummary(Transaction $transaction): array
    {
        $nextPayment = $this->findNextPendingPayment($transaction);
        $paidAmount = $transaction->getPaidAmount();
        $totalPrice = (float) ($transaction->getPrice() ?? 0);

        return [
            'transactionId' => $transaction->getId(),
            'totalPrice' => $totalPrice,
            'paidAmount' => $paidAmount,
            'remainingBalance' => max(0.0, $totalPrice - $paidAmount),
            'nextPaymentAmount' => $nextPayment?->getAmount(),
            'monthsLeft' => $transaction->countPendingInstallments(),
            'nextDueDate' => $nextPayment?->getDate()?->format(\DateTimeInterface::ATOM),
            'nextPaymentStatus' => $nextPayment?->getStatus(),
            'isFullyPaid' => $transaction->isFullyPaid(),
        ];
    }

    private function findNextPendingPayment(Transaction $transaction): ?Payment
    {
        $pending = array_values(array_filter(
            $transaction->getPayments()->toArray(),
            static fn (Payment $payment) => in_array(strtolower(trim((string) $payment->getStatus())), ['pending', 'submitted'], true)
        ));

        usort($pending, static function (Payment $a, Payment $b): int {
            $dateA = $a->getDate();
            $dateB = $b->getDate();

            return ($dateA?->getTimestamp() ?? 0) <=> ($dateB?->getTimestamp() ?? 0);
        });

        return $pending[0] ?? null;
    }
}
