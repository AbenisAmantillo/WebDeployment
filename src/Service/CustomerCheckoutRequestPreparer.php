<?php

namespace App\Service;

use App\Entity\Furniture;
use App\Entity\Property;
use App\Entity\Transaction;
use App\Entity\TransactionFurniture;
use App\Entity\User;
use App\Repository\FurnitureRepository;
use App\Repository\TransactionRepository;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class CustomerCheckoutRequestPreparer
{
    private const ALLOWED_PAYMENT_METHODS = ['debit_card', 'mobile_transfer', 'bank_transfer', 'cash'];
    private const ALLOWED_PURCHASE_TYPES = ['rent', 'buy'];

    public function __construct(
        private readonly FurnitureRepository $furnitureRepository,
        private readonly TransactionRepository $transactionRepository,
    ) {
    }

    public function prepare(Transaction $transaction, User $customer): void
    {
        $submittedCustomer = $transaction->getCustomer();
        if ($submittedCustomer instanceof User && $submittedCustomer !== $customer) {
            if ($submittedCustomer->getId() === null || $customer->getId() === null || $submittedCustomer->getId() !== $customer->getId()) {
                throw new AccessDeniedHttpException('You cannot create a transaction for another customer.');
            }
        }

        if ($this->transactionRepository->customerHasUnpaidTransaction($customer)) {
            throw new ConflictHttpException('Complete outstanding payments before starting a new checkout.');
        }

        $property = $transaction->getProperty();
        if (!$property instanceof Property) {
            throw new BadRequestHttpException('A property is required.');
        }

        if (strtolower(trim((string) $property->getStatus())) !== 'available') {
            throw new BadRequestHttpException('This property is no longer available.');
        }

        $purchaseType = strtolower(trim((string) ($transaction->getPurchaseType() ?: 'rent')));
        if (!in_array($purchaseType, self::ALLOWED_PURCHASE_TYPES, true)) {
            throw new BadRequestHttpException('purchaseType must be rent or buy.');
        }

        $paymentMethod = strtolower(trim((string) $transaction->getClientPaymentMethod()));
        if (!in_array($paymentMethod, self::ALLOWED_PAYMENT_METHODS, true)) {
            throw new BadRequestHttpException('Invalid clientPaymentMethod.');
        }

        $totalPrice = (float) ($property->getPrice() ?? 0);
        foreach ($this->normalizeFurnitureLines($transaction->getSelectedFurnitureLines()) as $line) {
            $furniture = $this->furnitureRepository->find($line['furnitureId']);
            if (!$furniture instanceof Furniture) {
                throw new BadRequestHttpException(sprintf('Furniture item %d was not found.', $line['furnitureId']));
            }

            if (strtolower(trim((string) $furniture->getStatus())) !== 'available') {
                throw new BadRequestHttpException(sprintf('Furniture item "%s" is no longer available.', $furniture->getName()));
            }

            $stock = $furniture->getStock();
            if ($stock !== null && $stock < $line['quantity']) {
                throw new BadRequestHttpException(sprintf(
                    'Insufficient stock for "%s". Available: %d, requested: %d.',
                    $furniture->getName(),
                    $stock,
                    $line['quantity']
                ));
            }

            $transactionFurniture = new TransactionFurniture();
            $transactionFurniture->setFurniture($furniture);
            $transactionFurniture->setQuantity($line['quantity']);
            $transaction->addTransactionFurniture($transactionFurniture);

            $totalPrice += (float) ($furniture->getPrice() ?? 0) * $line['quantity'];
        }

        $downpayment = $transaction->getClientDownpaymentAmount();
        if ($downpayment === null || $downpayment < 0.01) {
            throw new BadRequestHttpException('clientDownpaymentAmount must be at least 0.01.');
        }

        if ($downpayment > $totalPrice) {
            throw new BadRequestHttpException('clientDownpaymentAmount cannot exceed the total price.');
        }

        if ($purchaseType === 'rent') {
            $paymentPlanMonths = (int) ($transaction->getClientPaymentPlanMonths() ?? 0);
            if (!Transaction::isValidPaymentPlanMonths($paymentPlanMonths)) {
                throw new BadRequestHttpException('clientPaymentPlanMonths must be 12, 24, or 36.');
            }

            $transaction->setClientPaymentPlanMonths($paymentPlanMonths);
        } else {
            $transaction->setClientPaymentPlanMonths(null);
        }

        $transaction->setCustomer($customer);
        $transaction->setPurchaseType($purchaseType);
        $transaction->setDate(new \DateTime());
        $transaction->setPrice($totalPrice);
        $transaction->setClientPaymentMethod($paymentMethod);
    }

    /**
     * @param list<array<string, mixed>> $lines
     *
     * @return list<array{furnitureId: int, quantity: int}>
     */
    private function normalizeFurnitureLines(array $lines): array
    {
        $normalized = [];

        foreach ($lines as $line) {
            if (!is_array($line)) {
                continue;
            }

            $furnitureId = $this->extractId($line['furnitureId'] ?? $line['furniture'] ?? $line['id'] ?? null);
            $quantity = $line['quantity'] ?? 1;
            if ($furnitureId === null || !is_numeric((string) $quantity)) {
                continue;
            }

            $quantity = (int) $quantity;
            if ($quantity <= 0) {
                continue;
            }

            if (!isset($normalized[$furnitureId])) {
                $normalized[$furnitureId] = ['furnitureId' => $furnitureId, 'quantity' => 0];
            }

            $normalized[$furnitureId]['quantity'] += $quantity;
        }

        return array_values($normalized);
    }

    private function extractId(mixed $value): ?int
    {
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
}
