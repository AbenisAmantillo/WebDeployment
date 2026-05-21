<?php

namespace App\Tests\Service;

use App\Entity\Payment;
use App\Entity\Transaction;
use App\Entity\User;
use App\Repository\PaymentRepository;
use App\Service\PaymentSubmissionService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class PaymentSubmissionServiceTest extends TestCase
{
    public function testCustomerCanSubmitOwnPendingPaymentWithoutCompletingIt(): void
    {
        $customer = $this->createUser(1);
        $payment = $this->createPayment($customer, 'Pending');
        $submittedAt = new \DateTime('2026-05-26T10:00:00+00:00');

        $result = $this->createService($payment)
            ->submit($customer, 10, 'mobile_transfer', $submittedAt);

        self::assertSame($payment, $result);
        self::assertSame('Submitted', $payment->getStatus());
        self::assertSame('mobile_transfer', $payment->getPaymentMethod());
        self::assertSame($submittedAt, $payment->getDate());
    }

    public function testCustomerCannotSubmitAnotherCustomersPayment(): void
    {
        $payment = $this->createPayment($this->createUser(1), 'Pending');

        $this->expectException(AccessDeniedHttpException::class);
        $this->expectExceptionMessage('own payments');

        $this->createService($payment)->submit($this->createUser(2), 10, 'cash');
    }

    public function testCompletedPaymentCannotBeSubmitted(): void
    {
        $customer = $this->createUser(1);
        $payment = $this->createPayment($customer, 'Completed');

        $this->expectException(ConflictHttpException::class);
        $this->expectExceptionMessage('Only pending payments');

        $this->createService($payment)->submit($customer, 10, 'cash');
    }

    public function testInvalidPaymentMethodIsRejected(): void
    {
        $customer = $this->createUser(1);
        $payment = $this->createPayment($customer, 'Pending');

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('Invalid payment method');

        $this->createService($payment)->submit($customer, 10, 'crypto');
    }

    private function createService(Payment $payment): PaymentSubmissionService
    {
        $repository = $this->createMock(PaymentRepository::class);
        $repository->expects(self::once())
            ->method('find')
            ->with(10)
            ->willReturn($payment);

        return new PaymentSubmissionService($repository);
    }

    private function createPayment(User $customer, string $status): Payment
    {
        return (new Payment())
            ->setCustomer($customer)
            ->setTransaction(new Transaction())
            ->setAmount(1000)
            ->setPaymentMethod('cash')
            ->setStatus($status)
            ->setDate(new \DateTime('2026-05-26T00:00:00+00:00'));
    }

    private function createUser(int $id): User
    {
        $user = (new User())
            ->setUsername('user' . $id)
            ->setEmail('user' . $id . '@example.com')
            ->setPassword('hashed-password')
            ->setRoles(['ROLE_USER']);

        $reflection = new \ReflectionProperty(User::class, 'id');
        $reflection->setValue($user, $id);

        return $user;
    }
}
