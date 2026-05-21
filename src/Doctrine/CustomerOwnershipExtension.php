<?php

namespace App\Doctrine;

use ApiPlatform\Doctrine\Orm\Extension\QueryCollectionExtensionInterface;
use ApiPlatform\Doctrine\Orm\Extension\QueryItemExtensionInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use App\Entity\Payment;
use App\Entity\Transaction;
use App\Entity\TransactionFurniture;
use App\Entity\User;
use App\Security\ClientPortalAccessChecker;
use Doctrine\ORM\QueryBuilder;

/**
 * Limits transaction, payment, and transaction-furniture API reads to the authenticated customer.
 * Staff and administrators see all rows.
 */
final class CustomerOwnershipExtension implements QueryCollectionExtensionInterface, QueryItemExtensionInterface
{
    public function __construct(
        private readonly ClientPortalAccessChecker $clientPortalAccess,
    ) {
    }

    public function applyToCollection(
        QueryBuilder $queryBuilder,
        QueryNameGeneratorInterface $queryNameGenerator,
        string $resourceClass,
        ?Operation $operation = null,
        array $context = [],
    ): void {
        $this->applyScope($queryBuilder, $resourceClass);
    }

    public function applyToItem(
        QueryBuilder $queryBuilder,
        QueryNameGeneratorInterface $queryNameGenerator,
        string $resourceClass,
        array $identifiers,
        ?Operation $operation = null,
        array $context = [],
    ): void {
        $this->applyScope($queryBuilder, $resourceClass);
    }

    private function applyScope(QueryBuilder $queryBuilder, string $resourceClass): void
    {
        if (!in_array($resourceClass, [Transaction::class, Payment::class, TransactionFurniture::class], true)) {
            return;
        }

        if (!$this->clientPortalAccess->isCustomer()) {
            return;
        }

        $user = $this->clientPortalAccess->getCurrentUser();
        if (!$user instanceof User) {
            $queryBuilder->andWhere('1 = 0');

            return;
        }

        $rootAlias = $queryBuilder->getRootAliases()[0];

        if ($resourceClass === Transaction::class || $resourceClass === Payment::class) {
            $queryBuilder
                ->andWhere(sprintf('%s.customer = :current_user', $rootAlias))
                ->setParameter('current_user', $user);

            return;
        }

        $transactionJoinAlias = 'customer_scope_transaction';
        $queryBuilder
            ->join(sprintf('%s.transaction', $rootAlias), $transactionJoinAlias)
            ->andWhere(sprintf('%s.customer = :current_user', $transactionJoinAlias))
            ->setParameter('current_user', $user);
    }
}
