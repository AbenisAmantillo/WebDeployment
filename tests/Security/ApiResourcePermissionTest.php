<?php

namespace App\Tests\Security;

use ApiPlatform\Metadata\ApiResource;
use App\Entity\Furniture;
use App\Entity\Payment;
use App\Entity\Property;
use PHPUnit\Framework\TestCase;

final class ApiResourcePermissionTest extends TestCase
{
    public function testCustomersCannotPatchPropertyOrFurnitureDirectly(): void
    {
        self::assertOperationSecurityDoesNotAllowRoleUser(Property::class, 'PATCH');
        self::assertOperationSecurityDoesNotAllowRoleUser(Furniture::class, 'PATCH');
    }

    public function testCustomersCannotCreateOrConfirmPaymentsDirectly(): void
    {
        self::assertOperationSecurityDoesNotAllowRoleUser(Payment::class, 'POST');
        self::assertOperationSecurityDoesNotAllowRoleUser(Payment::class, 'PUT');
    }

    /**
     * @param class-string $resourceClass
     */
    private static function assertOperationSecurityDoesNotAllowRoleUser(string $resourceClass, string $method): void
    {
        $operations = self::getApiResourceOperations($resourceClass);
        $matched = false;

        foreach ($operations as $operation) {
            if ($operation->getMethod() !== $method) {
                continue;
            }

            $matched = true;
            self::assertStringNotContainsString('ROLE_USER', (string) $operation->getSecurity());
        }

        self::assertTrue($matched, sprintf('Expected %s operation on %s.', $method, $resourceClass));
    }

    /**
     * @param class-string $resourceClass
     *
     * @return iterable<object>
     */
    private static function getApiResourceOperations(string $resourceClass): iterable
    {
        $attributes = (new \ReflectionClass($resourceClass))->getAttributes(ApiResource::class);
        self::assertNotEmpty($attributes);

        return $attributes[0]->newInstance()->getOperations();
    }
}
