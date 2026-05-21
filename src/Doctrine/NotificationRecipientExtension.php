<?php

namespace App\Doctrine;

use ApiPlatform\Doctrine\Orm\Extension\QueryCollectionExtensionInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use App\Entity\Notification;
use App\Entity\User;
use App\Security\ClientPortalAccessChecker;
use Doctrine\ORM\QueryBuilder;

/**
 * Limits GET /api/notifications to rows for the authenticated client only.
 */
final class NotificationRecipientExtension implements QueryCollectionExtensionInterface
{
    public function __construct(
        private ClientPortalAccessChecker $clientPortalAccess,
    ) {
    }

    public function applyToCollection(
        QueryBuilder $queryBuilder,
        QueryNameGeneratorInterface $queryNameGenerator,
        string $resourceClass,
        ?Operation $operation = null,
        array $context = [],
    ): void {
        if ($resourceClass !== Notification::class) {
            return;
        }

        if ($this->clientPortalAccess->isStaffOrAdmin()) {
            $queryBuilder->andWhere('1 = 0');

            return;
        }

        $user = $this->clientPortalAccess->getCurrentUser();
        if (!$user instanceof User) {
            $queryBuilder->andWhere('1 = 0');

            return;
        }

        $rootAlias = $queryBuilder->getRootAliases()[0];
        $queryBuilder
            ->andWhere(sprintf('%s.recipient = :current_user', $rootAlias))
            ->setParameter('current_user', $user);
    }
}
