<?php

namespace App\Controller;

use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Annotation\Route;

class GoogleConnectController extends AbstractController
{
    #[Route('/connect/google', name: 'connect_google_start')]
    public function connect(
        ClientRegistry $clientRegistry,
        Request $request,
        SessionInterface $session
    ): Response {
        $mode = $request->query->get('mode', 'login');
        $role = $request->query->get('role');

        $session->set('oauth_mode', $mode);
        $session->set('oauth_signup_role', $role);

        return $clientRegistry
            ->getClient('google')
            ->redirect(['email', 'profile']);
    }

    #[Route('/connect/google/check', name: 'connect_google_check')]
    public function connectCheck(): Response
    {
        // This route can stay blank: the authenticator will intercept it.
        return new Response('Google callback');
    }
}