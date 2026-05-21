<?php

namespace App\Tests\Service;

use App\Entity\Payment;
use App\Service\StaffPaymentConfirmationService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class StaffPaymentConfirmationServiceTest extends TestCase
{
    public function testStaffCanConfirmSubmittedPayment(): void
    {
        $payment = (new Payment())
            ->setAmount(1000)
            ->setPaymentMethod('cash')
            ->setStatus('Submitted')
            ->setDate(new \DateTime());

        (new StaffPaymentConfirmationService())->confirmSubmittedPayment($payment);

        self::assertSame('Completed', $payment->getStatus());
    }

    public function testStaffCannotConfirmPendingPaymentWithoutClientSubmission(): void
    {
        $payment = (new Payment())
            ->setAmount(1000)
            ->setPaymentMethod('cash')
            ->setStatus('Pending')
            ->setDate(new \DateTime());

        $this->expectException(ConflictHttpException::class);
        $this->expectExceptionMessage('Only submitted payments');

        (new StaffPaymentConfirmationService())->confirmSubmittedPayment($payment);
    }
}
