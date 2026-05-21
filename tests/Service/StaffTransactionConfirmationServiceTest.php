<?php

namespace App\Tests\Service;

use App\Entity\Furniture;
use App\Entity\Payment;
use App\Entity\Property;
use App\Entity\Transaction;
use App\Entity\TransactionFurniture;
use App\Entity\User;
use App\Service\StaffTransactionConfirmationService;
use App\Service\TransactionPaymentRecorder;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

final class StaffTransactionConfirmationServiceTest extends TestCase
{
    public function testStaffConfirmationCreatesPaymentsUpdatesInventoryAndClearsSubmission(): void
    {
        $persistedPayments = [];
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('persist')
            ->willReturnCallback(static function (object $entity) use (&$persistedPayments): void {
                if ($entity instanceof Payment) {
                    $persistedPayments[] = $entity;
                }
            });

        $property = (new Property())
            ->setTitle('House')
            ->setStatus('available')
            ->setPrice(120_000)
            ->setAddress('Address')
            ->setImageFileName('house.jpg');

        $furniture = (new Furniture())
            ->setName('Chair')
            ->setStatus('available')
            ->setPrice(5_000)
            ->setStock(3);

        $transaction = (new Transaction())
            ->setCustomer($this->createCustomer())
            ->setProperty($property)
            ->setPurchaseType('rent')
            ->setPrice(125_000)
            ->setDate(new \DateTime())
            ->setClientDownpaymentAmount(25_000)
            ->setClientPaymentPlanMonths(12)
            ->setClientPaymentMethod('cash');

        $line = (new TransactionFurniture())
            ->setFurniture($furniture)
            ->setQuantity(2);
        $transaction->addTransactionFurniture($line);

        $service = new StaffTransactionConfirmationService(new TransactionPaymentRecorder($entityManager));
        $service->confirm($transaction);

        self::assertSame('sold', $property->getStatus());
        self::assertSame(1, $furniture->getStock());
        self::assertNull($transaction->getClientDownpaymentAmount());
        self::assertNull($transaction->getClientPaymentPlanMonths());
        self::assertNull($transaction->getClientPaymentMethod());
        self::assertSame(12, $transaction->getPaymentPlanMonths());
        self::assertCount(13, $persistedPayments);
        self::assertSame('Completed', $persistedPayments[0]->getStatus());
        self::assertSame(25_000.0, $persistedPayments[0]->getAmount());
        self::assertSame('Pending', $persistedPayments[1]->getStatus());
    }

    private function createCustomer(): User
    {
        return (new User())
            ->setUsername('customer')
            ->setEmail('customer@example.com')
            ->setPassword('hashed-password')
            ->setRoles(['ROLE_USER']);
    }
}
