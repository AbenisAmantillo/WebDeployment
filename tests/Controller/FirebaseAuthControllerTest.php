<?php

namespace App\Tests\Controller;

use App\Controller\FirebaseAuthController;
use App\Entity\User;
use App\Repository\UserRepository;
use App\Security\AppUserChecker;
use App\Security\JwtLoginResponseFactory;
use App\Service\Firebase\FirebaseAuthenticatedUser;
use App\Service\Firebase\FirebaseTokenMissingEmailException;
use App\Service\Firebase\FirebaseTokenVerifierInterface;
use App\Service\Firebase\InvalidFirebaseTokenException;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class FirebaseAuthControllerTest extends TestCase
{
    public function testValidFirebaseTokenReturnsJwtForExistingUser(): void
    {
        $firebaseUser = new FirebaseAuthenticatedUser(
            uid: 'firebase-uid-123',
            email: 'client@example.com',
            displayName: 'Client User',
            emailVerified: true,
        );

        $user = (new User())
            ->setEmail('client@example.com')
            ->setUsername('client')
            ->setRoles(['ROLE_USER'])
            ->setPassword('hashed-password')
            ->setIsVerified(true);

        $tokenVerifier = $this->createMock(FirebaseTokenVerifierInterface::class);
        $tokenVerifier->expects(self::once())
            ->method('verify')
            ->with('valid-firebase-token')
            ->willReturn($firebaseUser);

        $userRepository = $this->createMock(UserRepository::class);
        $userRepository->expects(self::once())
            ->method('findOneByEmail')
            ->with('client@example.com')
            ->willReturn($user);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::never())->method('persist');
        $entityManager->expects(self::never())->method('flush');

        $passwordHasher = $this->createMock(UserPasswordHasherInterface::class);
        $passwordHasher->expects(self::never())->method('hashPassword');

        $jwtManager = $this->createMock(JWTTokenManagerInterface::class);
        $jwtManager->expects(self::once())
            ->method('create')
            ->with($user)
            ->willReturn('backend-jwt');

        $response = $this->createController(
            $tokenVerifier,
            $userRepository,
            $entityManager,
            $passwordHasher,
            $jwtManager,
        )->__invoke($this->createJsonRequest(['idToken' => 'valid-firebase-token']));

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertSame([
            'token' => 'backend-jwt',
            'user' => [
                'id' => null,
                'username' => 'client',
                'email' => 'client@example.com',
                'roles' => ['ROLE_USER'],
                'verified' => true,
            ],
        ], $this->decodeResponse($response));
    }

    public function testFirebaseTokenCannotDowngradeExistingVerifiedUser(): void
    {
        $firebaseUser = new FirebaseAuthenticatedUser(
            uid: 'firebase-uid-123',
            email: 'client@example.com',
            displayName: 'Client User',
            emailVerified: false,
        );

        $user = (new User())
            ->setEmail('client@example.com')
            ->setUsername('client')
            ->setRoles(['ROLE_USER'])
            ->setPassword('hashed-password')
            ->setIsVerified(true);

        $tokenVerifier = $this->createMock(FirebaseTokenVerifierInterface::class);
        $tokenVerifier->expects(self::once())
            ->method('verify')
            ->with('stale-firebase-token')
            ->willReturn($firebaseUser);

        $userRepository = $this->createMock(UserRepository::class);
        $userRepository->expects(self::once())
            ->method('findOneByEmail')
            ->with('client@example.com')
            ->willReturn($user);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::never())->method('persist');
        $entityManager->expects(self::never())->method('flush');

        $jwtManager = $this->createMock(JWTTokenManagerInterface::class);
        $jwtManager->expects(self::once())
            ->method('create')
            ->with($user)
            ->willReturn('backend-jwt');

        $response = $this->createController(
            $tokenVerifier,
            $userRepository,
            $entityManager,
            jwtManager: $jwtManager,
        )->__invoke($this->createJsonRequest(['idToken' => 'stale-firebase-token']));

        self::assertTrue($user->isVerified());
        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertSame([
            'token' => 'backend-jwt',
            'user' => [
                'id' => null,
                'username' => 'client',
                'email' => 'client@example.com',
                'roles' => ['ROLE_USER'],
                'verified' => true,
            ],
        ], $this->decodeResponse($response));
    }

    public function testValidFirebaseTokenCreatesNewUser(): void
    {
        $firebaseUser = new FirebaseAuthenticatedUser(
            uid: 'firebase-uid-456',
            email: 'new-client@example.com',
            displayName: 'New Client',
            emailVerified: true,
        );

        $tokenVerifier = $this->createMock(FirebaseTokenVerifierInterface::class);
        $tokenVerifier->expects(self::once())
            ->method('verify')
            ->with('valid-firebase-token')
            ->willReturn($firebaseUser);

        $userRepository = $this->createMock(UserRepository::class);
        $userRepository->expects(self::once())
            ->method('findOneByEmail')
            ->with('new-client@example.com')
            ->willReturn(null);
        $userRepository->expects(self::once())
            ->method('findOneBy')
            ->with(['username' => 'NewClient'])
            ->willReturn(null);

        $passwordHasher = $this->createMock(UserPasswordHasherInterface::class);
        $passwordHasher->expects(self::once())
            ->method('hashPassword')
            ->with(self::isInstanceOf(User::class), self::isType('string'))
            ->willReturn('hashed-random-password');

        $persistedUser = null;
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())
            ->method('persist')
            ->with(self::callback(function (User $user) use (&$persistedUser): bool {
                $persistedUser = $user;

                self::assertSame('new-client@example.com', $user->getEmail());
                self::assertSame('NewClient', $user->getUsername());
                self::assertSame(['ROLE_USER'], $user->getRoles());
                self::assertTrue($user->isVerified());
                self::assertSame('hashed-random-password', $user->getPassword());

                return true;
            }));
        $entityManager->expects(self::once())->method('flush');

        $jwtManager = $this->createMock(JWTTokenManagerInterface::class);
        $jwtManager->expects(self::once())
            ->method('create')
            ->willReturnCallback(function (User $user) use (&$persistedUser): string {
                self::assertSame($persistedUser, $user);

                return 'backend-jwt';
            });

        $response = $this->createController(
            $tokenVerifier,
            $userRepository,
            $entityManager,
            $passwordHasher,
            $jwtManager,
        )->__invoke($this->createJsonRequest(['idToken' => 'valid-firebase-token']));

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertSame([
            'token' => 'backend-jwt',
            'user' => [
                'id' => null,
                'username' => 'NewClient',
                'email' => 'new-client@example.com',
                'roles' => ['ROLE_USER'],
                'verified' => true,
            ],
        ], $this->decodeResponse($response));
    }

    public function testInvalidFirebaseTokenReturnsUnauthorized(): void
    {
        $tokenVerifier = $this->createMock(FirebaseTokenVerifierInterface::class);
        $tokenVerifier->expects(self::once())
            ->method('verify')
            ->with('bad-token')
            ->willThrowException(new InvalidFirebaseTokenException());

        $response = $this->createController(tokenVerifier: $tokenVerifier)
            ->__invoke($this->createJsonRequest(['idToken' => 'bad-token']));

        self::assertSame(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
        self::assertSame([
            'message' => 'Invalid Firebase ID token.',
        ], $this->decodeResponse($response));
    }

    public function testMissingFirebaseTokenReturnsBadRequest(): void
    {
        $tokenVerifier = $this->createMock(FirebaseTokenVerifierInterface::class);
        $tokenVerifier->expects(self::never())->method('verify');

        $response = $this->createController(tokenVerifier: $tokenVerifier)
            ->__invoke($this->createJsonRequest([]));

        self::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        self::assertSame([
            'message' => 'Firebase idToken is required.',
        ], $this->decodeResponse($response));
    }

    public function testFirebaseTokenWithoutEmailReturnsBadRequest(): void
    {
        $tokenVerifier = $this->createMock(FirebaseTokenVerifierInterface::class);
        $tokenVerifier->expects(self::once())
            ->method('verify')
            ->with('token-without-email')
            ->willThrowException(new FirebaseTokenMissingEmailException());

        $response = $this->createController(tokenVerifier: $tokenVerifier)
            ->__invoke($this->createJsonRequest(['idToken' => 'token-without-email']));

        self::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        self::assertSame([
            'message' => 'Firebase token does not include an email address.',
        ], $this->decodeResponse($response));
    }

    private function createController(
        ?FirebaseTokenVerifierInterface $tokenVerifier = null,
        ?UserRepository $userRepository = null,
        ?EntityManagerInterface $entityManager = null,
        ?UserPasswordHasherInterface $passwordHasher = null,
        ?JWTTokenManagerInterface $jwtManager = null,
    ): FirebaseAuthController {
        $jwtManager ??= $this->createMock(JWTTokenManagerInterface::class);

        return new FirebaseAuthController(
            tokenVerifier: $tokenVerifier ?? $this->createMock(FirebaseTokenVerifierInterface::class),
            userRepository: $userRepository ?? $this->createMock(UserRepository::class),
            entityManager: $entityManager ?? $this->createMock(EntityManagerInterface::class),
            passwordHasher: $passwordHasher ?? $this->createMock(UserPasswordHasherInterface::class),
            userChecker: new AppUserChecker(),
            responseFactory: new JwtLoginResponseFactory($jwtManager),
        );
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function createJsonRequest(array $payload): Request
    {
        return new Request(content: json_encode($payload, JSON_THROW_ON_ERROR));
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeResponse(Response $response): array
    {
        return json_decode((string) $response->getContent(), true, 512, JSON_THROW_ON_ERROR);
    }
}
