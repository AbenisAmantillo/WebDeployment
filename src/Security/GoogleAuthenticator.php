<?php

namespace App\Security;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use KnpU\OAuth2ClientBundle\Security\Authenticator\OAuth2Authenticator;
use League\OAuth2\Client\Provider\GoogleUser;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class GoogleAuthenticator extends OAuth2Authenticator
{
    public function __construct(
        private ClientRegistry $clientRegistry,
        private UserRepository $userRepository,
        private EntityManagerInterface $entityManager,
        private UrlGeneratorInterface $urlGenerator,
        private UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    public function supports(Request $request): ?bool
    {
        return $request->attributes->get('_route') === 'connect_google_check';
    }

    public function authenticate(Request $request): Passport
    {
        $client = $this->clientRegistry->getClient('google');
        $accessToken = $this->fetchAccessToken($client);

        /** @var GoogleUser $googleUser */
        $googleUser = $client->fetchUserFromToken($accessToken);

        $email = $googleUser->getEmail();
        $session = $request->getSession();

        if (!$email) {
            throw new AuthenticationException('Google account has no email address.');
        }

        $oauthMode = $session->get('oauth_mode', 'login');
        $signupRole = $session->get('oauth_signup_role', 'user');

        return new SelfValidatingPassport(
            new UserBadge($email, function (string $userIdentifier) use ($oauthMode, $signupRole, $email, $session) {
                $existingUser = $this->userRepository->findOneBy(['email' => $userIdentifier]);

                if ($existingUser) {
                    $existingUser->setIsVerified(true);
                    $existingUser->setVerificationToken(null);
                    $this->entityManager->flush();

                    return $existingUser;
                }

                $roles = $oauthMode === 'signup'
                    ? $this->mapSignupRoleToSymfonyRoles($signupRole)
                    : ['ROLE_USER'];

                $user = $this->createOAuthUser($email, $roles);

                $user->setIsVerified(true);
                $user->setVerificationToken(null);
                $this->entityManager->persist($user);
                $this->entityManager->flush();

                return $user;
            })
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        $user = $token->getUser();

        $request->getSession()->remove('oauth_mode');
        $request->getSession()->remove('oauth_signup_role');

        $roles = $user->getRoles();

        if (in_array('ROLE_ADMIN', $roles, true) || in_array('ROLE_STAFF', $roles, true)) {
            return new RedirectResponse($this->urlGenerator->generate('app_dashboard'));
        }

        if (in_array('ROLE_DESIGNER', $roles, true)) {
            return new RedirectResponse($this->urlGenerator->generate('app_dashboard'));
        }

        if (in_array('ROLE_USER', $roles, true) || in_array('ROLE_CLIENT', $roles, true)) {
            return new RedirectResponse($this->urlGenerator->generate('app_client_dashboard'));
        }

        return new RedirectResponse($this->urlGenerator->generate('app_login'));
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        $session = $request->getSession();

        if ($session->get('oauth_account_created')) {
            $session->remove('oauth_account_created');
            $session->remove('oauth_verification_url');
            $emailFailed = (bool) $session->get('oauth_verification_email_failed');
            $session->remove('oauth_verification_email_failed');

            if ($emailFailed) {
                $session->getFlashBag()->add(
                    'error',
                    'Account created, but we could not send the verification email. Please contact support or try again later.'
                );
            } else {
                $session->getFlashBag()->add(
                    'success',
                    'Account created successfully. Please check your email (including spam) to verify your account before signing in.'
                );
            }

            return new RedirectResponse($this->urlGenerator->generate('app_login'));
        }

        $request->getSession()->getFlashBag()->add('error', $exception->getMessage());

        return new RedirectResponse($this->urlGenerator->generate('app_login'));
    }

    private function createOAuthUser(string $email, array $roles): User
    {
        $user = new User();
        $user->setEmail($email);

        $baseUsername = strstr($email, '@', true) ?: 'googleuser';
        $user->setUsername($this->generateUniqueUsername($baseUsername));
        $user->setRoles($roles);

        $randomPassword = bin2hex(random_bytes(16));
        $user->setPassword($this->passwordHasher->hashPassword($user, $randomPassword));

        return $user;
    }

    private function mapSignupRoleToSymfonyRoles(?string $signupRole): array
    {
        return match ($signupRole) {
            'admin' => ['ROLE_ADMIN'],
            'staff' => ['ROLE_STAFF'],
            'designer' => ['ROLE_DESIGNER'],
            'client', 'user', null => ['ROLE_USER'],
            default => ['ROLE_USER'],
        };
    }

    private function generateUniqueUsername(string $baseUsername): string
    {
        $baseUsername = preg_replace('/[^a-zA-Z0-9_]/', '', $baseUsername) ?: 'googleuser';
        $candidate = $baseUsername;
        $counter = 1;

        while ($this->userRepository->findOneBy(['username' => $candidate])) {
            $candidate = $baseUsername . $counter;
            $counter++;
        }

        return $candidate;
    }
}