<?php

namespace App\State;

use ApiPlatform\Metadata\HttpOperation;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Payment;
use App\Entity\Transaction;
use App\Entity\User;
use App\Service\ActivityLogService;
use App\Service\CustomerCheckoutRequestPreparer;
use App\Security\ClientPortalAccessChecker;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\AsDecorator;
use Symfony\Component\DependencyInjection\Attribute\AutowireDecorated;

/**
 * Logs client app (API) transaction and payment activity for the admin activity log.
 */
#[AsDecorator('api_platform.doctrine.orm.state.persist_processor')]
final class ActivityLogPersistProcessor implements ProcessorInterface
{
    public function __construct(
        #[AutowireDecorated]
        private ProcessorInterface $inner,
        private ActivityLogService $activityLogService,
        private Security $security,
        private ClientPortalAccessChecker $clientPortalAccess,
        private CustomerCheckoutRequestPreparer $checkoutRequestPreparer,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        if (
            $data instanceof Payment
            && $operation instanceof HttpOperation
            && $operation->getMethod() === 'PUT'
            && $this->clientPortalAccess->isCustomer()
        ) {
            $previous = $context['previous_data'] ?? null;
            if (!$previous instanceof Payment) {
                throw new \Symfony\Component\HttpKernel\Exception\BadRequestHttpException('Payment not found.');
            }

            $user = $this->clientPortalAccess->getCurrentUser();
            if (!$user instanceof User || $previous->getCustomer()?->getId() !== $user->getId()) {
                throw new \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException('You can only update your own payments.');
            }

            if (strtolower(trim((string) $previous->getStatus())) !== 'pending') {
                throw new \Symfony\Component\HttpKernel\Exception\BadRequestHttpException('Only pending payments can be updated.');
            }

            if (strtolower(trim((string) $data->getStatus())) !== 'completed') {
                throw new \Symfony\Component\HttpKernel\Exception\BadRequestHttpException('Customers may only mark pending payments as Completed.');
            }

            $data->setAmount($previous->getAmount());
            $data->setCustomer($previous->getCustomer());
            $data->setTransaction($previous->getTransaction());
        }

        $previousTransaction = ($data instanceof Transaction && isset($context['previous_data']) && $context['previous_data'] instanceof Transaction)
            ? $context['previous_data']
            : null;

        if (
            $data instanceof Transaction
            && $operation instanceof HttpOperation
            && $operation->getMethod() === 'POST'
            && $this->clientPortalAccess->isCustomer()
        ) {
            $user = $this->clientPortalAccess->getCurrentUser();
            if (!$user instanceof User) {
                throw new \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException('Authentication required.');
            }

            $this->checkoutRequestPreparer->prepare($data, $user);
        }

        $result = $this->inner->process($data, $operation, $uriVariables, $context);

        if (!$operation instanceof HttpOperation) {
            return $result;
        }

        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return $result;
        }

        $method = $operation->getMethod();

        if ($data instanceof Transaction) {
            if ($method === 'POST') {
                $propertyTitle = $data->getProperty()?->getTitle() ?? 'Unknown';
                $this->activityLogService->logActivity(
                    $user,
                    'Transaction created (client app) - Property: ' . $propertyTitle
                );

                if ($data->hasClientPaymentSubmission()) {
                    $this->activityLogService->logActivity(
                        $user,
                        'Payment submitted (client app) for Transaction #' . $data->getId()
                    );
                }
            } elseif (\in_array($method, ['PUT', 'PATCH'], true)) {
                $hadSubmission = $previousTransaction?->hasClientPaymentSubmission() ?? false;
                if (!$hadSubmission && $data->hasClientPaymentSubmission()) {
                    $this->activityLogService->logActivity(
                        $user,
                        'Payment submitted (client app) for Transaction #' . $data->getId()
                    );
                }
            }
        }

        if ($data instanceof Payment && $method === 'POST') {
            $transactionId = $data->getTransaction()?->getId() ?? 'unknown';
            $this->activityLogService->logActivity(
                $user,
                'Payment created (client app) for Transaction #' . $transactionId
            );
        }

        return $result;
    }
}
