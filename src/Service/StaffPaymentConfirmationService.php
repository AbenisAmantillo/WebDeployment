<?php

namespace App\Service;

use App\Entity\Payment;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class StaffPaymentConfirmationService
{
    public function confirmSubmittedPayment(Payment $payment): void
    {
        $status = strtolower(trim((string) $payment->getStatus()));

        if ($status === 'completed') {
            throw new ConflictHttpException('This payment is already completed.');
        }

        if ($status !== 'submitted') {
            throw new ConflictHttpException('Only submitted payments can be confirmed by staff.');
        }

        $payment->setStatus('Completed');
    }
}
