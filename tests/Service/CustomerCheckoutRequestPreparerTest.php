<?php

namespace App\Tests\Service;

use App\Entity\Furniture;
use App\Entity\Property;
use App\Entity\Transaction;
use App\Entity\User;
use App\Repository\FurnitureRepository;
use App\Repository\TransactionRepository;
use App\Service\CustomerCheckoutRequestPreparer;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

final class CustomerCheckoutRequestPreparerTest extends TestCase
{
    public function testCustomerCanCreatePendingCheckoutTransaction(): void
    {
        $customer = $this->createCustomer();
        $property = $this->createProperty('available', 100_000);
        $furniture = $this->createFurniture('available', 5_000, 3);
        $transaction = $this->createCheckoutTransaction($property);
        $transaction->setSelectedFurnitureLines([
            ['furnitureId' => 7, 'quantity' => 2],
        ]);

        $preparer = $this->createPreparer(furnitureById: [7 => $furniture]);
        $preparer->prepare($transaction, $customer);

        self::assertSame($customer, $transaction->getCustomer());
        self::assertSame('rent', $transaction->getPurchaseType());
        self::assertSame(110_000.0, $transaction->getPrice());
        self::assertSame('cash', $transaction->getClientPaymentMethod());
        self::assertTrue($transaction->hasClientPaymentSubmission());
        self::assertSame('available', $property->getStatus());
        self::assertSame(3, $furniture->getStock());
        self::assertCount(1, $transaction->getTransactionFurniture());
    }

    public function testCustomerCannotCreateTransactionForAnotherCustomer(): void
    {
        $transaction = $this->createCheckoutTransaction($this->createProperty('available', 100_000));
        $transaction->setCustomer($this->createCustomer());

        $this->expectException(AccessDeniedHttpException::class);
        $this->expectExceptionMessage('another customer');

        $this->createPreparer()->prepare($transaction, $this->createCustomer());
    }

    public function testInvalidPaymentPlanIsRejected(): void
    {
        $transaction = $this->createCheckoutTransaction($this->createProperty('available', 100_000));
        $transaction->setClientPaymentPlanMonths(6);

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('12, 24, or 36');

        $this->createPreparer()->prepare($transaction, $this->createCustomer());
    }

    public function testInvalidDownpaymentIsRejected(): void
    {
        $transaction = $this->createCheckoutTransaction($this->createProperty('available', 100_000));
        $transaction->setClientDownpaymentAmount(0);

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('at least 0.01');

        $this->createPreparer()->prepare($transaction, $this->createCustomer());
    }

    public function testInvalidPaymentMethodIsRejected(): void
    {
        $transaction = $this->createCheckoutTransaction($this->createProperty('available', 100_000));
        $transaction->setClientPaymentMethod('crypto');

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('Invalid clientPaymentMethod');

        $this->createPreparer()->prepare($transaction, $this->createCustomer());
    }

    /**
     * @param array<int, Furniture> $furnitureById
     */
    private function createPreparer(array $furnitureById = []): CustomerCheckoutRequestPreparer
    {
        $furnitureRepository = $this->createMock(FurnitureRepository::class);
        $furnitureRepository->method('find')
            ->willReturnCallback(static fn (int $id): ?Furniture => $furnitureById[$id] ?? null);

        $transactionRepository = $this->createMock(TransactionRepository::class);
        $transactionRepository->method('customerHasUnpaidTransaction')->willReturn(false);

        return new CustomerCheckoutRequestPreparer($furnitureRepository, $transactionRepository);
    }

    private function createCheckoutTransaction(Property $property): Transaction
    {
        return (new Transaction())
            ->setProperty($property)
            ->setPurchaseType('rent')
            ->setClientDownpaymentAmount(10_000)
            ->setClientPaymentPlanMonths(12)
            ->setClientPaymentMethod('cash');
    }

    private function createCustomer(): User
    {
        return (new User())
            ->setUsername('customer')
            ->setEmail('customer@example.com')
            ->setPassword('hashed-password')
            ->setRoles(['ROLE_USER']);
    }

    private function createProperty(string $status, float $price): Property
    {
        return (new Property())
            ->setTitle('House')
            ->setStatus($status)
            ->setPrice($price)
            ->setAddress('Address')
            ->setImageFileName('house.jpg');
    }

    private function createFurniture(string $status, float $price, ?int $stock): Furniture
    {
        return (new Furniture())
            ->setName('Chair')
            ->setStatus($status)
            ->setPrice($price)
            ->setStock($stock);
    }
}
