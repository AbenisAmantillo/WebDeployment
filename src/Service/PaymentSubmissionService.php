<?php

namespace App\Service;

use App\Entity\Payment;
use App\Entity\User;
use App\Repository\PaymentRepository;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class PaymentSubmissionService
{
    private const ALLOWED_PAYMENT_METHODS = ['debit_card', 'mobile_transfer', 'bank_transfer', 'cash'];

    public function __construct(
        private readonly PaymentRepository $paymentRepository,
    ) {
    }

    public function submit(
        User $customer,
        int $paymentId,
        string $paymentMethod,
        ?\DateTimeInterface $date = null,
    ): Payment {
        $payment = $this->paymentRepository->find($paymentId);
        if (!$payment instanceof Payment) {
            throw new NotFoundHttpException('Payment not found.');
        }

        if ($payment->getCustomer()?->getId() !== $customer->getId()) {
            throw new AccessDeniedHttpException('You can only submit your own payments.');
        }

        $status = strtolower(trim((string) $payment->getStatus()));
        if ($status !== 'pending') {
            throw new ConflictHttpException('Only pending payments can be submitted for staff confirmation.');
        }

        if (!in_array($paymentMethod, self::ALLOWED_PAYMENT_METHODS, true)) {
            throw new BadRequestHttpException('Invalid payment method.');
        }

        $payment->setPaymentMethod($paymentMethod);
        $payment->setDate($date ?? new \DateTime());
        $payment->setStatus('Submitted');

        return $payment;
    }
}
