<?php

namespace App\Controller;

use App\Entity\Payment;
use App\Entity\Transaction;
use App\Entity\TransactionFurniture;
use App\Form\PaymentType;
use App\Form\TransactionType;
use App\Repository\PaymentRepository;
use App\Repository\PropertyRepository;
use App\Repository\TransactionRepository;
use App\Repository\FurnitureRepository;
use App\Service\ActivityLogService;
use App\Service\StaffTransactionConfirmationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/transaction')]
final class TransactionController extends AbstractController
{
    #[Route('/', name: 'app_transaction_index', methods: ['GET'])]
    public function index(TransactionRepository $transactionRepository): Response
    {
        // Allow admin, staff, or user to view transactions
        if (!$this->isGranted('ROLE_ADMIN') && !$this->isGranted('ROLE_STAFF') && !$this->isGranted('ROLE_USER')) {
            throw $this->createAccessDeniedException('You need ROLE_ADMIN, ROLE_STAFF, or ROLE_USER to access transactions.');
        }

        $currentUser = $this->getUser();
        $userCanCreateTransaction = true;

        try {
            // Use query builder to ensure transactionFurniture and payments are loaded properly
            $transactions = $transactionRepository->createQueryBuilder('t')
                ->leftJoin('t.transactionFurniture', 'tf')
                ->addSelect('tf')
                ->leftJoin('tf.furniture', 'f')
                ->addSelect('f')
                ->leftJoin('t.customer', 'c')
                ->addSelect('c')
                ->leftJoin('t.property', 'p')
                ->addSelect('p')
                ->leftJoin('t.payments', 'pay')
                ->addSelect('pay')
                ->orderBy('t.date', 'DESC')
                ->getQuery()
                ->getResult();

            if ($currentUser && !$this->isGranted('ROLE_ADMIN')) {
                $userCanCreateTransaction = !$transactionRepository->customerHasUnpaidTransaction($currentUser);
            }
        } catch (\Doctrine\DBAL\Exception\TableNotFoundException $e) {
            throw $this->createNotFoundException('Database table not found. Please run migrations: php bin/console doctrine:migrations:migrate');
        } catch (\Doctrine\DBAL\Exception $e) {
            // If there's a database structure issue (e.g., column not found)
            // This might happen if migrations haven't been run
            $this->addFlash('error', 'Database structure mismatch. Please run migrations: php bin/console doctrine:migrations:migrate');
            // Return empty array to avoid further errors
            $transactions = [];
        }

        return $this->render('transaction/index.html.twig', [
            'transactions' => $transactions,
            'user_can_create_transaction' => $userCanCreateTransaction,
        ]);
    }

    #[Route('/new', name: 'app_transaction_new', methods: ['GET', 'POST'])]
    public function new(
        Request $request, 
        TransactionRepository $transactionRepository,
        EntityManagerInterface $entityManager,
        ActivityLogService $activityLogService,
        FurnitureRepository $furnitureRepository
    ): Response {
        $currentUser = $this->getUser();
        
        // Prevent admin from creating transactions
        if ($this->isGranted('ROLE_ADMIN')) {
            throw $this->createAccessDeniedException('Admin accounts cannot create transactions.');
        }
        
        // Allow staff or user only
        if (!$this->isGranted('ROLE_STAFF') && !$this->isGranted('ROLE_USER')) {
            throw $this->createAccessDeniedException('You need ROLE_STAFF or ROLE_USER to create transactions.');
        }
        
        if ($transactionRepository->customerHasUnpaidTransaction($currentUser)) {
            $this->addFlash('error', 'You have an active transaction that is not fully paid yet. Please complete all payments before creating a new transaction.');
            return $this->redirectToRoute('app_transaction_index');
        }
        
        $transaction = new Transaction();
        
        // Automatically set current logged-in user as customer
        $transaction->setCustomer($currentUser);
        $transaction->setDate(new \DateTime());

        // Get available furniture with stock > 0
        $availableFurniture = $furnitureRepository->createQueryBuilder('f')
            ->where('f.status = :available')
            ->andWhere('f.stock > 0 OR f.stock IS NULL')
            ->setParameter('available', 'available')
            ->orderBy('f.name', 'ASC')
            ->getQuery()
            ->getResult();

        $form = $this->createForm(TransactionType::class, $transaction, [
            'current_user' => $currentUser,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Always ensure the customer is set to the current logged-in user
            // This prevents any tampering with the hidden customer field
            $transaction->setCustomer($currentUser);
            
            $property = $transaction->getProperty();
            
            // Calculate total price: property price
            $totalPrice = $property->getPrice();
            
            // Handle furniture if user wants to add it
            $addFurniture = $request->request->get('add_furniture') === '1';
            if ($addFurniture) {
                $furnitureData = $request->request->all('furniture_quantities');
                
                foreach ($furnitureData as $furnitureId => $quantity) {
                    $quantity = (int) $quantity;
                    if ($quantity > 0) {
                        $furniture = $furnitureRepository->find($furnitureId);
                        if ($furniture && $furniture->getStatus() === 'available') {
                            // Check stock availability
                            $currentStock = $furniture->getStock() ?? 0;
                            if ($currentStock < $quantity) {
                                $this->addFlash('error', "Insufficient stock for {$furniture->getName()}. Available: {$currentStock}, Requested: {$quantity}");
                                return $this->render('transaction/new.html.twig', [
                                    'transaction' => $transaction,
                                    'form' => $form,
                                    'available_furniture' => $availableFurniture,
                                ]);
                            }
                            
                            // Create TransactionFurniture entry
                            $transactionFurniture = new TransactionFurniture();
                            $transactionFurniture->setTransaction($transaction);
                            $transactionFurniture->setFurniture($furniture);
                            $transactionFurniture->setQuantity($quantity);
                            $transaction->addTransactionFurniture($transactionFurniture);
                            
                            // Add to total price
                            $totalPrice += $furniture->getPrice() * $quantity;
                            
                            // Deduct stock
                            $newStock = $currentStock - $quantity;
                            $furniture->setStock($newStock);
                            
                            // Update status if stock is 0
                            if ($newStock <= 0) {
                                $furniture->setStatus('sold');
                            }
                        }
                    }
                }
            }
            
            $transaction->setPrice($totalPrice);
            
            // Update property status to sold
            $property->setStatus('sold');
            
            $entityManager->persist($transaction);
            $entityManager->flush();

            // Log transaction creation
            if ($currentUser) {
                $activityLogService->logActivity($currentUser, 'Transaction created - Property: ' . $property->getTitle());
            }

            $isRent = strtolower((string) $transaction->getPurchaseType()) === 'rent';
            $isStaff = $this->isGranted('ROLE_STAFF');
            $allowedMethods = ['debit_card', 'mobile_transfer', 'bank_transfer', 'cash'];
            $paymentMethodRaw = $request->request->get('payment_method');
            $paymentMethod = (is_scalar($paymentMethodRaw) && in_array((string) $paymentMethodRaw, $allowedMethods, true))
                ? (string) $paymentMethodRaw
                : null;

            if ($paymentMethod === null) {
                $this->addFlash('error', 'Please select a payment method.');
                return $this->redirectToRoute('app_transaction_process_payment', ['id' => $transaction->getId()]);
            }

            if ($isRent) {
                $downpaymentRaw = $request->request->get('amount');
                $paymentPlanRaw = $request->request->get('payment_plan');
                $downpayment = (is_scalar($downpaymentRaw) && is_numeric((string) $downpaymentRaw)) ? (float) $downpaymentRaw : null;
                $paymentPlan = (is_scalar($paymentPlanRaw) && preg_match('/^\d+$/', (string) $paymentPlanRaw)) ? (int) $paymentPlanRaw : null;

                if ($downpayment === null || $downpayment < 0 || $paymentPlan === null || !Transaction::isValidPaymentPlanMonths($paymentPlan)) {
                    $this->addFlash('error', 'Invalid payment details. Please enter downpayment, a 12/24/36-month plan, and payment method.');
                    return $this->redirectToRoute('app_transaction_process_payment', ['id' => $transaction->getId()]);
                }

                if ($downpayment > $totalPrice) {
                    $this->addFlash('error', 'Downpayment cannot exceed total price.');
                    return $this->redirectToRoute('app_transaction_process_payment', ['id' => $transaction->getId()]);
                }

                $transaction->setPaymentPlanMonths($paymentPlan);

                $remainingAmount = $totalPrice - $downpayment;
                $monthlyPayment = $paymentPlan > 0 ? ($remainingAmount / $paymentPlan) : 0;

                $downPayment = new Payment();
                $downPayment->setTransaction($transaction);
                $downPayment->setCustomer($transaction->getCustomer());
                $downPayment->setAmount($downpayment);
                $downPayment->setPaymentMethod($paymentMethod);
                $downPayment->setStatus('Completed');
                $downPayment->setDate(new \DateTime());
                $entityManager->persist($downPayment);

                $currentDate = new \DateTime();
                for ($month = 1; $month <= $paymentPlan; $month++) {
                    $monthlyPaymentRecord = new Payment();
                    $monthlyPaymentRecord->setTransaction($transaction);
                    $monthlyPaymentRecord->setCustomer($transaction->getCustomer());
                    $monthlyPaymentRecord->setAmount($monthlyPayment);
                    $monthlyPaymentRecord->setPaymentMethod($paymentMethod);
                    $monthlyPaymentRecord->setStatus('Pending');
                    $paymentDate = clone $currentDate;
                    $paymentDate->modify("+{$month} months");
                    $monthlyPaymentRecord->setDate($paymentDate);
                    $entityManager->persist($monthlyPaymentRecord);
                }
            } else {
                $payment = new Payment();
                $payment->setTransaction($transaction);
                $payment->setCustomer($transaction->getCustomer());
                $payment->setAmount($totalPrice);
                $payment->setPaymentMethod($paymentMethod);
                $payment->setStatus('Completed');
                $payment->setDate(new \DateTime());
                $entityManager->persist($payment);
            }

            $entityManager->flush();

            if ($currentUser) {
                $activityLogService->logActivity(
                    $currentUser,
                    ($isStaff ? 'Payment received for Transaction #' : 'Payment processed for Transaction #') . $transaction->getId()
                );
            }

            $this->addFlash(
                'success',
                $isStaff ? 'Transaction created and payment received successfully!' : 'Transaction created and payment processed successfully!'
            );
            return $this->redirectToRoute('app_transaction_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('transaction/new.html.twig', [
            'transaction' => $transaction,
            'form' => $form,
            'available_furniture' => $availableFurniture,
        ]);
    }

    #[Route('/{id}/receive-payment', name: 'app_transaction_receive_payment', methods: ['POST'])]
    public function receivePayment(
        Request $request,
        Transaction $transaction,
        EntityManagerInterface $entityManager,
        StaffTransactionConfirmationService $confirmationService,
        ActivityLogService $activityLogService
    ): Response {
        if (!$this->isGranted('ROLE_STAFF') && !$this->isGranted('ROLE_ADMIN')) {
            throw $this->createAccessDeniedException('Only staff or admin can receive payments.');
        }

        if (!$this->isCsrfTokenValid('receive_payment_' . $transaction->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid security token.');
            return $this->redirectToRoute('app_transaction_index');
        }

        try {
            $confirmationService->confirm($transaction);
        } catch (HttpExceptionInterface $exception) {
            $this->addFlash('error', $exception->getMessage());
            return $this->redirectToRoute('app_transaction_index');
        }

        $entityManager->flush();

        if ($this->getUser()) {
            $activityLogService->logActivity(
                $this->getUser(),
                'Payment received for Transaction #' . $transaction->getId()
            );
        }

        $this->addFlash('success', 'Payment received and recorded successfully!');
        return $this->redirectToRoute('app_transaction_index');
    }

    #[Route('/{id}/process-payment', name: 'app_transaction_process_payment', methods: ['GET', 'POST'])]
    public function processPayment(
        Request $request,
        Transaction $transaction,
        EntityManagerInterface $entityManager,
        ActivityLogService $activityLogService
    ): Response {
        $currentUser = $this->getUser();
        $isStaffOrAdmin = $this->isGranted('ROLE_STAFF') || $this->isGranted('ROLE_ADMIN');

        // Users: can only pay their own transaction.
        // Staff/Admin: allowed here to RECORD/CONFIRM payment/downpayment received.
        if (!$isStaffOrAdmin) {
            if (!$currentUser || $transaction->getCustomer()?->getId() !== $currentUser->getId()) {
                throw $this->createAccessDeniedException('You can only process payment for your own transaction.');
            }
        }

        // Check if transaction already has payments or awaits staff confirmation from client app
        if ($transaction->getPayments()->count() > 0) {
            $this->addFlash('warning', 'Payment has already been processed for this transaction.');
            return $this->redirectToRoute('app_transaction_index');
        }

        if ($isStaffOrAdmin) {
            return $this->redirectToRoute('app_transaction_index');
        }

        $isRent = strtolower($transaction->getPurchaseType()) === 'rent';
        $totalPrice = $transaction->getPrice();

        // Create a payment entity for form binding
        $payment = new Payment();
        $payment->setTransaction($transaction);
        $payment->setCustomer($transaction->getCustomer());
        $payment->setDate(new \DateTime());

        $form = $this->createForm(PaymentType::class, $payment, [
            'is_rent' => $isRent,
            'total_price' => $totalPrice,
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $paymentMethod = $payment->getPaymentMethod();
            
            if ($isRent) {
                // Handle rental payment: downpayment + monthly payments
                $downpayment = $form->get('downpayment')->getData();
                $paymentPlan = $form->get('paymentPlan')->getData(); // months
                
                // Validate downpayment
                if ($downpayment > $totalPrice) {
                    $this->addFlash('error', 'Downpayment cannot exceed total price.');
                    return $this->render('transaction/process_payment.html.twig', [
                        'transaction' => $transaction,
                        'form' => $form,
                        'is_rent' => $isRent,
                        'total_price' => $totalPrice,
                    ]);
                }

                if (!Transaction::isValidPaymentPlanMonths((int) $paymentPlan)) {
                    $this->addFlash('error', 'Please select a 12, 24, or 36 month payment plan.');
                    return $this->render('transaction/process_payment.html.twig', [
                        'transaction' => $transaction,
                        'form' => $form,
                        'is_rent' => $isRent,
                        'total_price' => $totalPrice,
                    ]);
                }

                $transaction->setPaymentPlanMonths((int) $paymentPlan);

                $remainingAmount = $totalPrice - $downpayment;
                $monthlyPayment = $remainingAmount / $paymentPlan;

                // Create downpayment payment
                $downPayment = new Payment();
                $downPayment->setTransaction($transaction);
                $downPayment->setCustomer($transaction->getCustomer());
                $downPayment->setAmount($downpayment);
                $downPayment->setPaymentMethod($paymentMethod);
                $downPayment->setStatus('Completed');
                $downPayment->setDate(new \DateTime());
                $entityManager->persist($downPayment);

                // Create monthly payment records
                $currentDate = new \DateTime();
                for ($month = 1; $month <= $paymentPlan; $month++) {
                    $monthlyPaymentRecord = new Payment();
                    $monthlyPaymentRecord->setTransaction($transaction);
                    $monthlyPaymentRecord->setCustomer($transaction->getCustomer());
                    $monthlyPaymentRecord->setAmount($monthlyPayment);
                    $monthlyPaymentRecord->setPaymentMethod($paymentMethod);
                    $monthlyPaymentRecord->setStatus('Pending'); // Monthly payments start as pending
                    // Set payment date for each month
                    $paymentDate = clone $currentDate;
                    $paymentDate->modify("+{$month} months");
                    $monthlyPaymentRecord->setDate($paymentDate);
                    $entityManager->persist($monthlyPaymentRecord);
                }

            } else {
                // For buy transactions: single payment
                $payment->setAmount($totalPrice);
                $payment->setStatus('Completed');
                $entityManager->persist($payment);
            }

            $entityManager->flush();

            // Log payment processing
            if ($this->getUser()) {
                $activityLogService->logActivity(
                    $this->getUser(),
                    ($isStaffOrAdmin ? 'Payment received for Transaction #' : 'Payment processed for Transaction #') . $transaction->getId()
                );
            }

            $this->addFlash('success', $isStaffOrAdmin ? 'Payment received and recorded successfully!' : 'Payment processed successfully!');
            return $this->redirectToRoute('app_transaction_index');
        }

        return $this->render('transaction/process_payment.html.twig', [
            'transaction' => $transaction,
            'form' => $form,
            'is_rent' => $isRent,
            'total_price' => $totalPrice,
        ]);
    }

    #[Route('/clear-all', name: 'app_transaction_clear_all', methods: ['POST'])]
    public function clearAll(
        Request $request,
        PaymentRepository $paymentRepository,
        TransactionRepository $transactionRepository,
        PropertyRepository $propertyRepository,
        EntityManagerInterface $entityManager,
        ActivityLogService $activityLogService
    ): Response {
        // Verify CSRF token
        if (!$this->isCsrfTokenValid('clear_all_transactions', $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid security token.');
            return $this->redirectToRoute('app_transaction_index');
        }

        try {
            // Delete all payments first (they reference transactions)
            $payments = $paymentRepository->findAll();
            $paymentCount = count($payments);
            foreach ($payments as $payment) {
                $entityManager->remove($payment);
            }
            $entityManager->flush();

            // Delete all transactions (this will cascade to transaction_furniture)
            $transactions = $transactionRepository->findAll();
            $transactionCount = count($transactions);
            
            foreach ($transactions as $transaction) {
                $entityManager->remove($transaction);
            }
            $entityManager->flush();

            // Reset all properties to available
            $properties = $propertyRepository->findAll();
            $propertyCount = 0;
            foreach ($properties as $property) {
                $property->setStatus('available');
                $propertyCount++;
            }
            $entityManager->flush();

            // Log the action
            if ($this->getUser()) {
                $activityLogService->logActivity(
                    $this->getUser(),
                    "Cleared all transactions ({$transactionCount} deleted), payments ({$paymentCount} deleted), and reset {$propertyCount} properties to available"
                );
            }

            $this->addFlash('success', "Successfully cleared {$paymentCount} payment(s), {$transactionCount} transaction(s), and reset {$propertyCount} property/properties to available status.");
        } catch (\Exception $e) {
            $this->addFlash('error', 'An error occurred while clearing transactions: ' . $e->getMessage());
        }

        return $this->redirectToRoute('app_transaction_index');
    }
}




