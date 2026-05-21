<?php

namespace App\Controller;

use App\Entity\Payment;
use App\Repository\PaymentRepository;
use App\Service\ActivityLogService;
use App\Service\StaffPaymentConfirmationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/payment')]
final class PaymentController extends AbstractController
{
    #[Route(name: 'app_payment_index', methods: ['GET'])]
    public function index(PaymentRepository $paymentRepository): Response
    {
        return $this->render('payment/index.html.twig', [
            'payments' => $paymentRepository->findStaffPaymentRecords(),
        ]);
    }

    #[Route('/{id}', name: 'app_payment_show', methods: ['GET'])]
    public function show(Payment $payment, PaymentRepository $paymentRepository): Response
    {
        $transaction = $payment->getTransaction();
        if (!$transaction) {
            throw $this->createNotFoundException('Transaction not found for this payment.');
        }

        $transactionPayments = $paymentRepository->createQueryBuilder('p')
            ->andWhere('p.transaction = :transaction')
            ->setParameter('transaction', $transaction)
            ->orderBy('p.date', 'ASC')
            ->getQuery()
            ->getResult();

        $totalPaid = 0.0;

        foreach ($transactionPayments as $p) {
            if (strtolower((string) $p->getStatus()) === 'completed') {
                $totalPaid += (float) $p->getAmount();
            }
        }

        $totalPrice = (float) ($transaction->getPrice() ?? 0);
        $remainingBalance = max(0.0, $totalPrice - $totalPaid);
        $paymentPlanMonths = $transaction->getResolvedPaymentPlanMonths();
        $pendingCount = $transaction->countPendingInstallments();

        return $this->render('payment/show.html.twig', [
            'payment' => $payment,
            'transaction' => $transaction,
            'transaction_payments' => $transactionPayments,
            'total_price' => $totalPrice,
            'total_paid' => $totalPaid,
            'remaining_balance' => $remainingBalance,
            'payment_plan_months' => $paymentPlanMonths,
            'pending_count' => $pendingCount,
        ]);
    }

    #[Route('/{id}/confirm-submitted', name: 'app_payment_confirm_submitted', methods: ['POST'])]
    public function confirmSubmitted(
        Request $request,
        Payment $payment,
        StaffPaymentConfirmationService $confirmationService,
        EntityManagerInterface $entityManager,
        ActivityLogService $activityLogService,
    ): Response {
        if (!$this->isCsrfTokenValid('confirm_submitted_payment_' . $payment->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid security token.');
            return $this->redirectToRoute('app_payment_index');
        }

        try {
            $confirmationService->confirmSubmittedPayment($payment);
            $entityManager->flush();

            if ($this->getUser()) {
                $activityLogService->logActivity(
                    $this->getUser(),
                    'Submitted installment payment confirmed for Payment #' . $payment->getId()
                );
            }

            $this->addFlash('success', 'Submitted payment confirmed successfully.');
        } catch (\Throwable $exception) {
            $this->addFlash('error', $exception->getMessage());
        }

        return $this->redirectToRoute('app_payment_index');
    }

    #[Route('/clear-all', name: 'app_payment_clear_all', methods: ['POST'])]
    public function clearAll(
        Request $request,
        PaymentRepository $paymentRepository,
        EntityManagerInterface $entityManager,
        ActivityLogService $activityLogService
    ): Response {
        // Verify CSRF token
        if (!$this->isCsrfTokenValid('clear_all_payments', $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid security token.');
            return $this->redirectToRoute('app_payment_index');
        }

        try {
            // Delete all payments
            $payments = $paymentRepository->findAll();
            $paymentCount = count($payments);
            
            foreach ($payments as $payment) {
                $entityManager->remove($payment);
            }
            $entityManager->flush();

            // Log the action
            if ($this->getUser()) {
                $activityLogService->logActivity(
                    $this->getUser(),
                    "Cleared all payments ({$paymentCount} deleted)"
                );
            }

            $this->addFlash('success', "Successfully cleared {$paymentCount} payment record(s).");
        } catch (\Exception $e) {
            $this->addFlash('error', 'An error occurred while clearing payments: ' . $e->getMessage());
        }

        return $this->redirectToRoute('app_payment_index');
    }
}




