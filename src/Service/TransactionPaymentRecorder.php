<?php

namespace App\Service;

use App\Entity\Payment;
use App\Entity\Transaction;
use Doctrine\ORM\EntityManagerInterface;

final class TransactionPaymentRecorder
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function recordRentPayments(
        Transaction $transaction,
        float $downpayment,
        int $paymentPlanMonths,
        string $paymentMethod,
    ): void {
        $totalPrice = (float) ($transaction->getPrice() ?? 0);
        $remainingAmount = max(0.0, $totalPrice - $downpayment);

        if (!Transaction::isValidPaymentPlanMonths($paymentPlanMonths)) {
            throw new \InvalidArgumentException('Payment plan must be 12, 24, or 36 months for rent.');
        }

        $transaction->setPaymentPlanMonths($paymentPlanMonths);

        $downPayment = new Payment();
        $downPayment->setTransaction($transaction);
        $downPayment->setCustomer($transaction->getCustomer());
        $downPayment->setAmount($downpayment);
        $downPayment->setPaymentMethod($paymentMethod);
        $downPayment->setStatus('Completed');
        $downPayment->setDate(new \DateTime());
        $this->entityManager->persist($downPayment);

        // Only schedule installments when there is a remaining balance after the downpayment.
        if ($remainingAmount <= 0.01) {
            return;
        }

        $monthlyPayment = $remainingAmount / $paymentPlanMonths;
        $currentDate = new \DateTime();

        for ($month = 1; $month <= $paymentPlanMonths; $month++) {
            $monthlyPaymentRecord = new Payment();
            $monthlyPaymentRecord->setTransaction($transaction);
            $monthlyPaymentRecord->setCustomer($transaction->getCustomer());
            $monthlyPaymentRecord->setAmount($monthlyPayment);
            $monthlyPaymentRecord->setPaymentMethod($paymentMethod);
            $monthlyPaymentRecord->setStatus('Pending');
            $paymentDate = clone $currentDate;
            $paymentDate->modify("+{$month} months");
            $monthlyPaymentRecord->setDate($paymentDate);
            $this->entityManager->persist($monthlyPaymentRecord);
        }
    }

    public function recordBuyPayment(Transaction $transaction, string $paymentMethod): void
    {
        $payment = new Payment();
        $payment->setTransaction($transaction);
        $payment->setCustomer($transaction->getCustomer());
        $payment->setAmount((float) ($transaction->getPrice() ?? 0));
        $payment->setPaymentMethod($paymentMethod);
        $payment->setStatus('Completed');
        $payment->setDate(new \DateTime());
        $this->entityManager->persist($payment);
    }

    public function clearClientSubmission(Transaction $transaction): void
    {
        $transaction->setClientDownpaymentAmount(null);
        $transaction->setClientPaymentPlanMonths(null);
        $transaction->setClientPaymentMethod(null);
    }
}
