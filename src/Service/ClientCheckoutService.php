<?php

namespace App\Service;

use App\Entity\Property;
use App\Entity\Transaction;
use App\Entity\User;
use App\Repository\PropertyRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class ClientCheckoutService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly PropertyRepository $propertyRepository,
        private readonly CustomerCheckoutRequestPreparer $checkoutRequestPreparer,
        private readonly ActivityLogService $activityLogService,
    ) {
    }

    /**
     * @param array<int, array{furnitureId: int, quantity: int}> $furnitureLines
     */
    public function checkout(
        User $customer,
        int $propertyId,
        array $furnitureLines,
        float $downpayment,
        int $paymentPlanMonths,
        string $paymentMethod,
    ): Transaction {
        $property = $this->propertyRepository->find($propertyId);
        if (!$property instanceof Property) {
            throw new NotFoundHttpException('Property not found.');
        }

        $transaction = new Transaction();
        $transaction->setProperty($property);
        $transaction->setPurchaseType('rent');
        $transaction->setClientDownpaymentAmount($downpayment);
        $transaction->setClientPaymentPlanMonths($paymentPlanMonths);
        $transaction->setClientPaymentMethod($paymentMethod);
        $transaction->setSelectedFurnitureLines($furnitureLines);

        $this->checkoutRequestPreparer->prepare($transaction, $customer);

        $this->entityManager->persist($transaction);
        $this->entityManager->flush();

        $this->activityLogService->logActivity(
            $customer,
            'Checkout submitted (client app) - Property: ' . ($property->getTitle() ?? 'Unknown')
        );

        return $transaction;
    }
}
