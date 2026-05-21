<?php

namespace App\Service;

use App\Entity\Furniture;
use App\Entity\Transaction;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class StaffTransactionConfirmationService
{
    private const ALLOWED_PAYMENT_METHODS = ['debit_card', 'mobile_transfer', 'bank_transfer', 'cash'];

    public function __construct(
        private readonly TransactionPaymentRecorder $paymentRecorder,
    ) {
    }

    public function confirm(Transaction $transaction): void
    {
        if ($transaction->getPayments()->count() > 0) {
            throw new ConflictHttpException('Payment has already been processed for this transaction.');
        }

        $details = $transaction->getStaffReceivePaymentDetails();
        if ($details === null) {
            throw new BadRequestHttpException('Client downpayment, payment plan, and payment method are required before staff can receive payment.');
        }

        $paymentMethod = strtolower(trim($details['payment_method']));
        if (!in_array($paymentMethod, self::ALLOWED_PAYMENT_METHODS, true)) {
            throw new BadRequestHttpException('Invalid client payment method.');
        }

        $property = $transaction->getProperty();
        if ($property === null) {
            throw new BadRequestHttpException('Transaction property is required.');
        }

        if (!in_array(strtolower(trim((string) $property->getStatus())), ['available', 'reserved'], true)) {
            throw new ConflictHttpException('This property is no longer available for confirmation.');
        }

        foreach ($transaction->getTransactionFurniture() as $transactionFurniture) {
            $furniture = $transactionFurniture->getFurniture();
            if (!$furniture instanceof Furniture) {
                throw new BadRequestHttpException('Selected furniture item is missing.');
            }

            if (strtolower(trim((string) $furniture->getStatus())) !== 'available') {
                throw new ConflictHttpException(sprintf('Furniture item "%s" is no longer available.', $furniture->getName()));
            }

            $quantity = (int) ($transactionFurniture->getQuantity() ?? 0);
            $stock = $furniture->getStock();
            if ($quantity <= 0 || ($stock !== null && $stock < $quantity)) {
                throw new ConflictHttpException(sprintf('Insufficient stock for "%s".', $furniture->getName()));
            }
        }

        $totalPrice = (float) ($transaction->getPrice() ?? 0);
        if ($details['is_rent']) {
            if ($details['downpayment'] < 0.01) {
                throw new BadRequestHttpException('Downpayment must be at least 0.01.');
            }

            if ($details['downpayment'] > $totalPrice) {
                throw new BadRequestHttpException('Downpayment exceeds total price.');
            }

            if (!Transaction::isValidPaymentPlanMonths($details['payment_plan_months'])) {
                throw new BadRequestHttpException('Payment plan must be 12, 24, or 36 months.');
            }

            $this->paymentRecorder->recordRentPayments(
                $transaction,
                $details['downpayment'],
                $details['payment_plan_months'],
                $paymentMethod
            );
        } else {
            $this->paymentRecorder->recordBuyPayment($transaction, $paymentMethod);
        }

        foreach ($transaction->getTransactionFurniture() as $transactionFurniture) {
            $furniture = $transactionFurniture->getFurniture();
            if (!$furniture instanceof Furniture) {
                continue;
            }

            $stock = $furniture->getStock();
            if ($stock === null) {
                continue;
            }

            $newStock = $stock - (int) $transactionFurniture->getQuantity();
            $furniture->setStock($newStock);
            if ($newStock <= 0) {
                $furniture->setStatus('sold');
            }
        }

        $property->setStatus('sold');
        $this->paymentRecorder->clearClientSubmission($transaction);
    }
}
