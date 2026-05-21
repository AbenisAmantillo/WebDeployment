<?php

namespace App\Controller;

use App\Entity\Payment;
use App\Security\ClientPortalAccessChecker;
use App\Service\PaymentSubmissionService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class ApiPaymentSubmissionController extends AbstractController
{
    public function __construct(
        private readonly ClientPortalAccessChecker $clientPortalAccess,
        private readonly PaymentSubmissionService $paymentSubmissionService,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('/api/payments/{id}/submit', name: 'api_payment_submit', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[Route('/api/client/payments/{id}/submit', name: 'api_client_payment_submit', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function __invoke(int $id, Request $request): JsonResponse
    {
        try {
            $user = $this->clientPortalAccess->assertCustomerPortalAccess();
        } catch (\Symfony\Component\Security\Core\Exception\AccessDeniedException $exception) {
            return $this->json(['message' => $exception->getMessage()], Response::HTTP_FORBIDDEN);
        }

        $data = json_decode($request->getContent(), true);
        if (!is_array($data)) {
            return $this->json(['message' => 'Invalid JSON body'], Response::HTTP_BAD_REQUEST);
        }

        $paymentMethod = (string) ($data['paymentMethod'] ?? '');
        if ($paymentMethod === '') {
            return $this->json(['message' => 'paymentMethod is required'], Response::HTTP_BAD_REQUEST);
        }

        $date = null;
        if (!empty($data['date']) && is_string($data['date'])) {
            try {
                $date = new \DateTime($data['date']);
            } catch (\Exception) {
                return $this->json(['message' => 'Invalid date format'], Response::HTTP_BAD_REQUEST);
            }
        }

        try {
            $payment = $this->paymentSubmissionService->submit($user, $id, $paymentMethod, $date);
        } catch (AccessDeniedHttpException $exception) {
            return $this->json(['message' => $exception->getMessage()], Response::HTTP_FORBIDDEN);
        } catch (HttpExceptionInterface $exception) {
            return $this->json(['message' => $exception->getMessage()], $exception->getStatusCode());
        }

        $this->entityManager->flush();

        return $this->json([
            'payment' => $this->serializePayment($payment),
        ]);
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
            'customer' => $payment->getCustomer()?->getId(),
        ];
    }
}
