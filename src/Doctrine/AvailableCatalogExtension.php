<?php

namespace App\Doctrine;

use ApiPlatform\Doctrine\Orm\Extension\QueryCollectionExtensionInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use App\Entity\Furniture;
use App\Entity\Property;
use App\Security\ClientPortalAccessChecker;
use Doctrine\ORM\QueryBuilder;

/**
 * Limits property and furniture catalog collections to available inventory for customers.
 */
final class AvailableCatalogExtension implements QueryCollectionExtensionInterface
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
        if (!$this->clientPortalAccess->isCustomer()) {
            return;
        }

        $rootAlias = $queryBuilder->getRootAliases()[0];

        if ($resourceClass === Property::class) {
            $queryBuilder
                ->andWhere(sprintf('LOWER(%s.status) = :available_status', $rootAlias))
                ->setParameter('available_status', 'available');

            return;
        }

        if ($resourceClass === Furniture::class) {
            $queryBuilder
                ->andWhere(sprintf('LOWER(%s.status) = :available_status', $rootAlias))
                ->andWhere(sprintf('(%s.stock IS NULL OR %s.stock > 0)', $rootAlias, $rootAlias))
                ->setParameter('available_status', 'available');
        }
    }
}
