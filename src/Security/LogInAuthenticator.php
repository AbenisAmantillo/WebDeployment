<?php

namespace App\Security;

use App\Repository\UserRepository;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Http\Authenticator\AbstractLoginFormAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\CsrfTokenBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\RememberMeBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\PasswordCredentials;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\SecurityRequestAttributes;
use Symfony\Component\Security\Http\Util\TargetPathTrait;

class LogInAuthenticator extends AbstractLoginFormAuthenticator
{
    use TargetPathTrait;

    public const LOGIN_ROUTE = 'app_login';

    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
        private UserRepository $userRepository
    ) {
    }

    public function authenticate(Request $request): Passport
    {
        $login = trim((string) $request->request->get('login', ''));
        $password = (string) $request->request->get('password', '');

        $request->getSession()->set(SecurityRequestAttributes::LAST_USERNAME, $login);

        return new Passport(
            new UserBadge($login, function (string $userIdentifier) {
                $user = filter_var($userIdentifier, FILTER_VALIDATE_EMAIL)
                    ? $this->userRepository->findOneBy(['email' => $userIdentifier])
                    : $this->userRepository->findOneBy(['username' => $userIdentifier]);

                if (!$user) {
                    throw new UserNotFoundException('Invalid username/email.');
                }

                return $user;
            }),
            new PasswordCredentials($password),
            [
                new CsrfTokenBadge('authenticate', (string) $request->request->get('_csrf_token')),
                new RememberMeBadge(),
            ]
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        if ($targetPath = $this->getTargetPath($request->getSession(), $firewallName)) {
            return new RedirectResponse($targetPath);
        }

        $user = $token->getUser();
        $roles = $user->getRoles();

        if (in_array('ROLE_ADMIN', $roles, true)) {
            return new RedirectResponse($this->urlGenerator->generate('app_dashboard'));
        }

        if (in_array('ROLE_STAFF', $roles, true)) {
            return new RedirectResponse($this->urlGenerator->generate('app_dashboard'));
        }

        if (in_array('ROLE_USER', $roles, true)) {
            return new RedirectResponse($this->urlGenerator->generate('app_client_dashboard'));
        }

        return new RedirectResponse($this->urlGenerator->generate(self::LOGIN_ROUTE));
    }

    protected function getLoginUrl(Request $request): string
    {
        return $this->urlGenerator->generate(self::LOGIN_ROUTE);
    }
}