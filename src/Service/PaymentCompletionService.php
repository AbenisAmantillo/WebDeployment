<?php

namespace App\Service;

use App\Entity\Payment;
use App\Entity\User;
use App\Repository\PaymentRepository;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class PaymentCompletionService
{
    private const ALLOWED_PAYMENT_METHODS = ['debit_card', 'mobile_transfer', 'bank_transfer', 'cash'];

    public function __construct(
        private readonly PaymentRepository $paymentRepository,
    ) {
    }

    /**
     * @return array{payment: Payment, alreadyCompleted: bool}
     */
    public function completePayment(
        User $customer,
        int $paymentId,
        string $paymentMethod,
        ?\DateTimeInterface $date = null,
    ): array {
        $payment = $this->paymentRepository->find($paymentId);
        if (!$payment instanceof Payment) {
            throw new NotFoundHttpException('Payment not found.');
        }

        if ($payment->getCustomer()?->getId() !== $customer->getId()) {
            throw new AccessDeniedHttpException('You can only complete your own payments.');
        }

        $status = strtolower(trim((string) $payment->getStatus()));
        if ($status === 'completed') {
            return ['payment' => $payment, 'alreadyCompleted' => true];
        }

        if ($status !== 'pending') {
            throw new BadRequestHttpException('Only pending payments can be completed.');
        }

        if (!in_array($paymentMethod, self::ALLOWED_PAYMENT_METHODS, true)) {
            throw new BadRequestHttpException('Invalid payment method.');
        }

        $payment->setStatus('Completed');
        $payment->setPaymentMethod($paymentMethod);
        $payment->setDate($date ?? new \DateTime());

        return ['payment' => $payment, 'alreadyCompleted' => false];
    }
}
