<?php

namespace App\Controller;

use App\Entity\Payment;
use App\Entity\Transaction;
use App\Entity\TransactionFurniture;
use App\Form\AccountProfileType;
use App\Form\ClientChangePasswordType;
use App\Form\ClientInstallmentPaymentType;
use App\Form\PaymentType;
use App\Entity\User;
use App\Repository\PropertyRepository;
use App\Repository\FurnitureRepository;
use App\Repository\PaymentRepository;
use App\Repository\TransactionRepository;
use App\Service\ActivityLogService;
use App\Service\PaymentSubmissionService;
use App\Service\UserProfileImageService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
class ClientDashboardController extends AbstractController
{
    #[Route('/client_dashboard', name: 'app_client_dashboard')]
    public function index(
        PropertyRepository $propertyRepository,
        FurnitureRepository $furnitureRepository,
        PaymentRepository $paymentRepository,
        TransactionRepository $transactionRepository
    ): Response {
        if ($this->isGranted('ROLE_STAFF') || $this->isGranted('ROLE_ADMIN')) {
        throw $this->createAccessDeniedException('Staff and administrators cannot access the client dashboard.');
    }
        // Client should only see available properties
        $featuredProperties = $propertyRepository->findBy(['status' => 'available'], ['id' => 'DESC'], 6);
        
        // Get all properties for payment form dropdown
        $allProperties = $propertyRepository->findBy(['status' => 'available'], ['id' => 'DESC']);
        
        // Get available furniture - handle both "Available" and "available" status
        try {
            $availableFurniture = $furnitureRepository->findBy(['status' => 'Available'], ['id' => 'DESC'], 6);
            if (empty($availableFurniture)) {
                // Try lowercase if no results
                $availableFurniture = $furnitureRepository->findBy(['status' => 'available'], ['id' => 'DESC'], 6);
            }
        } catch (\Exception $e) {
            // If there's an error, just get all furniture
            $availableFurniture = $furnitureRepository->findBy([], ['id' => 'DESC'], 6);
        }
        
        // Get completed payments only for payment history
        $payments = $paymentRepository->findBy(['status' => 'Completed'], ['date' => 'DESC']);
        
        // Get statistics
        try {
            $totalProperties = count($propertyRepository->findAll());
            $totalFurniture = count($furnitureRepository->findAll());
        } catch (\Exception $e) {
            $totalProperties = 0;
            $totalFurniture = 0;
        }
        
        return $this->render('client_dashboard/index.html.twig', [
            'featured_properties' => $featuredProperties,
            'all_properties' => $allProperties,
            'available_furniture' => $availableFurniture,
            'total_properties' => $totalProperties,
            'total_furniture' => $totalFurniture,
            'payments' => $payments,
            'has_transaction' => $this->clientHasTransaction($transactionRepository),
            'can_create_transaction' => $this->clientCanCreateTransaction($transactionRepository),
        ]);
    }

    #[Route('/client/my-transactions', name: 'app_client_my_transactions', methods: ['GET'])]
    public function myTransactions(TransactionRepository $transactionRepository): Response
    {
        if ($this->isGranted('ROLE_STAFF') || $this->isGranted('ROLE_ADMIN')) {
            throw $this->createAccessDeniedException('Staff and administrators cannot access the client transactions page.');
        }

        $currentUser = $this->getUser();
        $transactions = [];

        if ($currentUser) {
            $transactions = $transactionRepository->createQueryBuilder('t')
                ->leftJoin('t.transactionFurniture', 'tf')
                ->addSelect('tf')
                ->leftJoin('tf.furniture', 'f')
                ->addSelect('f')
                ->leftJoin('t.property', 'p')
                ->addSelect('p')
                ->leftJoin('t.payments', 'pay')
                ->addSelect('pay')
                ->where('t.customer = :user')
                ->setParameter('user', $currentUser)
                ->orderBy('t.date', 'DESC')
                ->getQuery()
                ->getResult();
        }

        return $this->render('client_dashboard/my_transactions.html.twig', [
            'transactions' => $transactions,
            'has_transaction' => count($transactions) > 0,
            'can_create_transaction' => $this->clientCanCreateTransaction($transactionRepository),
        ]);
    }

    #[Route('/client/payments/{propertyId}', name: 'app_client_payments', requirements: ['propertyId' => '\d+'], defaults: ['propertyId' => null], methods: ['GET'])]
    public function payments(
        Request $request,
        PropertyRepository $propertyRepository,
        FurnitureRepository $furnitureRepository,
        TransactionRepository $transactionRepository,
        ?int $propertyId = null
    ): Response {
        if ($this->isGranted('ROLE_STAFF') || $this->isGranted('ROLE_ADMIN')) {
            throw $this->createAccessDeniedException('Staff and administrators cannot access the client payments page.');
        }

        if (!$this->clientCanCreateTransaction($transactionRepository)) {
            $this->addFlash('warning', 'You must fully pay your current transaction before starting a new one.');
            return $this->redirectToRoute('app_client_my_transactions');
        }

        $selectedPropertyId = $propertyId;
        if ($selectedPropertyId === null) {
            $qp = $request->query->get('property');
            if (is_scalar($qp) && preg_match('/^\d+$/', (string) $qp)) {
                $selectedPropertyId = (int) $qp;
            }
        }

        $allProperties = $propertyRepository->findAll();
        // Client should only be able to select available properties
        $allProperties = $propertyRepository->findBy(['status' => 'available'], ['id' => 'DESC']);
        $selectedProperty = $selectedPropertyId ? $propertyRepository->find($selectedPropertyId) : null;
        if ($selectedProperty && strtolower((string) $selectedProperty->getStatus()) !== 'available') {
            $this->addFlash('error', 'This property is no longer available.');
            $selectedProperty = null;
        }

        // Furniture selection (client): available items only
        try {
            $availableFurniture = $furnitureRepository->findBy(['status' => 'Available'], ['id' => 'DESC'], 50);
            if (empty($availableFurniture)) {
                $availableFurniture = $furnitureRepository->findBy(['status' => 'available'], ['id' => 'DESC'], 50);
            }
        } catch (\Exception $e) {
            $availableFurniture = $furnitureRepository->findBy([], ['id' => 'DESC'], 50);
        }

        return $this->render('client_dashboard/payments.html.twig', [
            'all_properties' => $allProperties,
            'selected_property' => $selectedProperty,
            'available_furniture' => $availableFurniture,
            'can_create_transaction' => true,
            'has_transaction' => $this->clientHasTransaction($transactionRepository),
        ]);
    }

    #[Route('/client/transactions', name: 'app_client_create_transaction', methods: ['POST'])]
    public function createTransaction(
        Request $request,
        PropertyRepository $propertyRepository,
        FurnitureRepository $furnitureRepository,
        TransactionRepository $transactionRepository,
        EntityManagerInterface $entityManager,
        ActivityLogService $activityLogService
    ): Response {
        if ($this->isGranted('ROLE_STAFF') || $this->isGranted('ROLE_ADMIN')) {
            throw $this->createAccessDeniedException('Staff and administrators cannot create client transactions.');
        }

        if (!$this->isCsrfTokenValid('client_create_transaction', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid security token. Please try again.');
            return $this->redirectToRoute('app_client_dashboard');
        }

        $currentUser = $this->getUser();
        if (!$currentUser) {
            throw $this->createAccessDeniedException('You must be logged in.');
        }

        if ($transactionRepository->customerHasUnpaidTransaction($currentUser)) {
            $this->addFlash('error', 'You have an active transaction that is not fully paid yet. Please complete all payments before creating a new transaction.');
            return $this->redirectToRoute('app_client_my_transactions');
        }

        $propertyId = $request->request->get('property_id', $request->request->get('property'));
        if (!is_scalar($propertyId) || !preg_match('/^\d+$/', (string) $propertyId)) {
            $this->addFlash('error', 'Please select a valid property.');
            return $this->redirectToRoute('app_client_payments');
        }

        $property = $propertyRepository->find((int) $propertyId);
        if (!$property) {
            $this->addFlash('error', 'Selected property was not found.');
            return $this->redirectToRoute('app_client_payments');
        }

        // Prevent transaction for non-available property
        if (strtolower((string) $property->getStatus()) !== 'available') {
            $this->addFlash('error', 'This property is no longer available.');
            return $this->redirectToRoute('app_client_payments', ['propertyId' => $property->getId()]);
        }

        $transaction = new Transaction();
        $transaction->setCustomer($currentUser);
        $transaction->setProperty($property);
        $transaction->setPurchaseType('rent');
        $transaction->setDate(new \DateTime());

        // Base total = property price
        $totalPrice = (float) ($property->getPrice() ?? 0);

        // Furniture quantities from client UI
        $furnitureData = $request->request->all('furniture_quantities');
        if (is_array($furnitureData)) {
            foreach ($furnitureData as $furnitureId => $quantity) {
                if (!preg_match('/^\d+$/', (string) $furnitureId)) {
                    continue;
                }
                $qty = (int) $quantity;
                if ($qty <= 0) {
                    continue;
                }

                $furniture = $furnitureRepository->find((int) $furnitureId);
                if (!$furniture) {
                    continue;
                }

                if (strtolower(trim((string) $furniture->getStatus())) !== 'available') {
                    continue;
                }

                // Stock check (if stock is set)
                $currentStock = $furniture->getStock();
                if ($currentStock !== null && $currentStock < $qty) {
                    $this->addFlash('error', "Insufficient stock for {$furniture->getName()}. Available: {$currentStock}, Requested: {$qty}");
                    return $this->redirectToRoute('app_client_payments', ['propertyId' => $property->getId()]);
                }

                $transactionFurniture = new TransactionFurniture();
                $transactionFurniture->setTransaction($transaction);
                $transactionFurniture->setFurniture($furniture);
                $transactionFurniture->setQuantity($qty);
                $transaction->addTransactionFurniture($transactionFurniture);
                $entityManager->persist($transactionFurniture);

                $totalPrice += (float) ($furniture->getPrice() ?? 0) * $qty;

                // Deduct stock if stock is tracked
                if ($currentStock !== null) {
                    $newStock = $currentStock - $qty;
                    $furniture->setStock($newStock);
                    if ($newStock <= 0) {
                        $furniture->setStatus('sold');
                    }
                }
            }
        }

        $transaction->setPrice($totalPrice);

        // Mark property as sold (same as TransactionController::new)
        $property->setStatus('sold');

        $entityManager->persist($transaction);
        $entityManager->flush();

        $activityLogService->logActivity($currentUser, 'Transaction created (client) - Property: ' . $property->getTitle());

        // Record payment immediately from the client "Make a Payment" form so admin tabs can read it.
        $downpaymentRaw = $request->request->get('amount');
        $paymentPlanRaw = $request->request->get('payment_plan');
        $paymentMethodRaw = $request->request->get('payment_method');

        $allowedMethods = ['debit_card', 'mobile_transfer', 'bank_transfer', 'cash'];
        $downpayment = (is_scalar($downpaymentRaw) && is_numeric((string) $downpaymentRaw)) ? (float) $downpaymentRaw : null;
        $paymentPlan = (is_scalar($paymentPlanRaw) && preg_match('/^\d+$/', (string) $paymentPlanRaw)) ? (int) $paymentPlanRaw : null;
        $paymentMethod = (is_scalar($paymentMethodRaw) && in_array((string) $paymentMethodRaw, $allowedMethods, true)) ? (string) $paymentMethodRaw : null;

        if ($downpayment === null || $downpayment < 0 || $paymentPlan === null || !Transaction::isValidPaymentPlanMonths($paymentPlan) || $paymentMethod === null) {
            $this->addFlash('error', 'Invalid payment details. Please enter downpayment, a 12/24/36-month plan, and payment method.');
            return $this->redirectToRoute('app_client_transaction_process_payment', ['id' => $transaction->getId()]);
        }

        if ($downpayment > $totalPrice) {
            $this->addFlash('error', 'Downpayment cannot exceed total price.');
            return $this->redirectToRoute('app_client_transaction_process_payment', ['id' => $transaction->getId()]);
        }

        $transaction->setClientDownpaymentAmount($downpayment);
        $transaction->setClientPaymentPlanMonths($paymentPlan);
        $transaction->setClientPaymentMethod($paymentMethod);
        $entityManager->flush();

        $activityLogService->logActivity($currentUser, 'Payment submitted (client) for Transaction #' . $transaction->getId());

        $this->addFlash('success', 'Transaction created. Payment details submitted — awaiting staff confirmation.');
        return $this->redirectToRoute('app_client_dashboard');
    }

    #[Route('/client/transactions/{id}/process-payment', name: 'app_client_transaction_process_payment', methods: ['GET', 'POST'])]
    public function processPaymentClient(
        Request $request,
        Transaction $transaction,
        EntityManagerInterface $entityManager,
        ActivityLogService $activityLogService
    ): Response {
        if ($this->isGranted('ROLE_STAFF') || $this->isGranted('ROLE_ADMIN')) {
            throw $this->createAccessDeniedException('Staff and administrators cannot access client payment processing.');
        }

        $currentUser = $this->getUser();
        if (!$currentUser || $transaction->getCustomer()?->getId() !== $currentUser->getId()) {
            throw $this->createAccessDeniedException('You can only access your own transaction.');
        }

        if ($transaction->getPayments()->count() > 0 || $transaction->hasClientPaymentSubmission()) {
            $this->addFlash('warning', 'Payment has already been submitted for this transaction.');
            return $this->redirectToRoute('app_client_dashboard');
        }

        $isRent = strtolower((string) $transaction->getPurchaseType()) === 'rent';
        $totalPrice = (float) ($transaction->getPrice() ?? 0);

        $payment = new Payment();
        $payment->setTransaction($transaction);
        $payment->setCustomer($transaction->getCustomer());
        $payment->setDate(new \DateTime());

        $form = $this->createForm(PaymentType::class, $payment, [
            'is_rent' => $isRent,
            'total_price' => $totalPrice,
        ]);

        if ($request->isMethod('GET') && $isRent) {
            $down = $request->query->get('downpayment');
            if (is_scalar($down) && is_numeric((string) $down)) {
                $form->get('downpayment')->setData((float) $down);
            }
            $plan = $request->query->get('payment_plan');
            if (is_scalar($plan) && preg_match('/^\d+$/', (string) $plan)) {
                $form->get('paymentPlan')->setData((int) $plan);
            }
        }

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $paymentMethod = $payment->getPaymentMethod();

            if ($isRent) {
                $downpayment = (float) $form->get('downpayment')->getData();
                $paymentPlan = (int) $form->get('paymentPlan')->getData();

                if ($downpayment > $totalPrice) {
                    $this->addFlash('error', 'Downpayment cannot exceed total price.');
                    return $this->render('client_dashboard/process_payment.html.twig', [
                        'transaction' => $transaction,
                        'form' => $form,
                        'is_rent' => $isRent,
                        'total_price' => $totalPrice,
                        'has_transaction' => true,
                    ]);
                }

                if (!Transaction::isValidPaymentPlanMonths($paymentPlan)) {
                    $this->addFlash('error', 'Please select a 12, 24, or 36 month payment plan.');
                    return $this->render('client_dashboard/process_payment.html.twig', [
                        'transaction' => $transaction,
                        'form' => $form,
                        'is_rent' => $isRent,
                        'total_price' => $totalPrice,
                        'has_transaction' => true,
                    ]);
                }

                $transaction->setClientDownpaymentAmount($downpayment);
                $transaction->setClientPaymentPlanMonths($paymentPlan);
            } else {
                $transaction->setClientDownpaymentAmount($totalPrice);
                $transaction->setClientPaymentPlanMonths(null);
            }

            $transaction->setClientPaymentMethod($paymentMethod);
            $entityManager->flush();

            if ($this->getUser()) {
                $activityLogService->logActivity(
                    $this->getUser(),
                    'Payment submitted (client) for Transaction #' . $transaction->getId()
                );
            }

            $this->addFlash('success', 'Payment details submitted — awaiting staff confirmation.');
            return $this->redirectToRoute('app_client_dashboard');
        }

        return $this->render('client_dashboard/process_payment.html.twig', [
            'transaction' => $transaction,
            'form' => $form,
            'is_rent' => $isRent,
            'total_price' => $totalPrice,
            'has_transaction' => true,
        ]);
    }

    #[Route('/client/my-payments', name: 'app_client_my_payments', methods: ['GET'])]
    public function myPayments(TransactionRepository $transactionRepository): Response
    {
        $this->assertClientOnly();

        $currentUser = $this->getUser();
        $activeTransaction = null;

        if ($currentUser instanceof User) {
            $activeTransaction = $transactionRepository->findMostRecentOutstandingTransaction($currentUser);
            if ($activeTransaction === null) {
                $transactions = $transactionRepository->findByCustomer($currentUser);
                $activeTransaction = $transactions[0] ?? null;
            }
        }

        return $this->render('client_dashboard/my_payments.html.twig', [
            'active_transaction' => $activeTransaction,
            'installment_form' => $this->createForm(ClientInstallmentPaymentType::class),
            'has_transaction' => $this->clientHasTransaction($transactionRepository),
            'can_create_transaction' => $this->clientCanCreateTransaction($transactionRepository),
        ]);
    }

    #[Route('/client/my-payments/{paymentId}/complete', name: 'app_client_my_payments_complete', requirements: ['paymentId' => '\d+'], methods: ['POST'])]
    public function completeMyPayment(
        int $paymentId,
        Request $request,
        PaymentSubmissionService $paymentSubmissionService,
        EntityManagerInterface $entityManager,
        ActivityLogService $activityLogService,
    ): Response {
        $this->assertClientOnly();

        $currentUser = $this->getUser();
        if (!$currentUser instanceof User) {
            throw $this->createAccessDeniedException('You must be logged in.');
        }

        $form = $this->createForm(ClientInstallmentPaymentType::class);
        $form->handleRequest($request);

        if (!$form->isSubmitted() || !$form->isValid()) {
            $this->addFlash('error', 'Please select a valid payment method.');
            return $this->redirectToRoute('app_client_my_payments');
        }

        $paymentMethod = (string) $form->get('paymentMethod')->getData();

        try {
            $paymentSubmissionService->submit($currentUser, $paymentId, $paymentMethod);
            $entityManager->flush();
            $activityLogService->logActivity($currentUser, 'Installment payment submitted for Payment #' . $paymentId);
            $this->addFlash('success', 'Installment payment submitted. Staff will confirm it shortly.');
        } catch (\Throwable $exception) {
            $this->addFlash('error', $exception->getMessage());
        }

        return $this->redirectToRoute('app_client_my_payments');
    }

    #[Route('/client/profile', name: 'app_client_profile', methods: ['GET', 'POST'])]
    public function profile(
        Request $request,
        TransactionRepository $transactionRepository,
        UserProfileImageService $profileImageService,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $entityManager,
        ActivityLogService $activityLogService,
    ): Response {
        $this->assertClientOnly();

        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException('You must be logged in.');
        }

        $profileForm = $this->createForm(AccountProfileType::class, $user);
        $passwordForm = $this->createForm(ClientChangePasswordType::class);

        $profileForm->handleRequest($request);
        if ($profileForm->isSubmitted() && $profileForm->isValid()) {
            $uploaded = $profileForm->get('profileImage')->getData();
            if ($uploaded) {
                try {
                    $profileImageService->replaceProfileImage($user, $uploaded);
                } catch (\InvalidArgumentException $e) {
                    $this->addFlash('error', $e->getMessage());

                    return $this->render('client_dashboard/profile.html.twig', [
                        'user' => $user,
                        'profile_form' => $profileForm,
                        'password_form' => $passwordForm->createView(),
                        'has_transaction' => $this->clientHasTransaction($transactionRepository),
                        'can_create_transaction' => $this->clientCanCreateTransaction($transactionRepository),
                    ]);
                } catch (FileException) {
                    $this->addFlash('error', 'Failed to upload profile photo. Please try again.');

                    return $this->render('client_dashboard/profile.html.twig', [
                        'user' => $user,
                        'profile_form' => $profileForm,
                        'password_form' => $passwordForm->createView(),
                        'has_transaction' => $this->clientHasTransaction($transactionRepository),
                        'can_create_transaction' => $this->clientCanCreateTransaction($transactionRepository),
                    ]);
                }
            } elseif ($profileForm->get('removeProfileImage')->getData()) {
                $profileImageService->clearProfileImage($user);
            }

            $entityManager->flush();
            $activityLogService->logActivity($user, 'Updated client profile');
            $this->addFlash('success', 'Your profile has been updated.');

            return $this->redirectToRoute('app_client_profile');
        }

        $passwordForm->handleRequest($request);
        if ($passwordForm->isSubmitted() && $passwordForm->isValid()) {
            $currentPassword = (string) $passwordForm->get('currentPassword')->getData();
            $newPassword = (string) $passwordForm->get('newPassword')->getData();

            if (!$passwordHasher->isPasswordValid($user, $currentPassword)) {
                $this->addFlash('error', 'Current password is incorrect.');
            } else {
                $user->setPassword($passwordHasher->hashPassword($user, $newPassword));
                $entityManager->flush();
                $activityLogService->logActivity($user, 'Changed account password');
                $this->addFlash('success', 'Password updated successfully.');
            }

            return $this->redirectToRoute('app_client_profile');
        }

        return $this->render('client_dashboard/profile.html.twig', [
            'user' => $user,
            'profile_form' => $profileForm,
            'password_form' => $passwordForm->createView(),
            'has_transaction' => $this->clientHasTransaction($transactionRepository),
            'can_create_transaction' => $this->clientCanCreateTransaction($transactionRepository),
        ]);
    }

    private function assertClientOnly(): void
    {
        if ($this->isGranted('ROLE_STAFF') || $this->isGranted('ROLE_ADMIN')) {
            throw $this->createAccessDeniedException('Staff and administrators cannot access the client dashboard.');
        }
    }

    private function clientHasTransaction(TransactionRepository $transactionRepository): bool
    {
        $currentUser = $this->getUser();
        if (!$currentUser) {
            return false;
        }

        return count($transactionRepository->findByCustomer($currentUser)) > 0;
    }

    private function clientCanCreateTransaction(TransactionRepository $transactionRepository): bool
    {
        $currentUser = $this->getUser();
        if (!$currentUser) {
            return false;
        }

        return !$transactionRepository->customerHasUnpaidTransaction($currentUser);
    }
}