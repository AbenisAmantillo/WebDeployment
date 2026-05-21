<?php

namespace App\Controller;

use App\Entity\User;
use App\Service\EmailVerificationService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class EmailVerificationController extends AbstractController
{
    public function __construct(
        #[Autowire('%env(MAILER_DSN)%')]
        private readonly string $mailerDsn,
    ) {
    }

    private function shouldExposeVerificationLink(): bool
    {
        if ($this->getParameter('kernel.environment') !== 'prod') {
            return true;
        }

        return str_contains($this->mailerDsn, 'mailpit')
            || str_contains($this->mailerDsn, 'null://null');
    }

    #[Route('/verify-email', name: 'app_verify_email')]
    public function verifyUserEmail(
    Request $request,
    EmailVerificationService $emailVerificationService
    ): Response {
        $token = $request->query->get('token');

        if (!$token) {
            $this->addFlash('error', 'Verification token is missing.');
            return $this->redirectToRoute('app_register');
        }

        $user = $emailVerificationService->verifyToken($token);

        if (!$user) {
            $this->addFlash('error', 'Invalid or expired verification token.');
            return $this->redirectToRoute('app_register');
        }

        $this->addFlash('success', 'Account verified successfully. You can now log in.');

        return $this->redirectToRoute('app_login');
    }

    #[Route('/verify-email/resend', name: 'app_email_verify_resend', methods: ['POST'])]
    public function resendVerification(
        Request $request,
        EmailVerificationService $emailVerificationService,
        EntityManagerInterface $entityManager,
        LoggerInterface $logger,
    ): Response {
        $csrfToken = (string) $request->request->get('_csrf_token', '');
        if (!$this->isCsrfTokenValid('resend_verification', $csrfToken)) {
            $this->addFlash('error', 'Invalid CSRF token. Please try again.');

            return $this->redirectToRoute('app_login');
        }

        $identifier = trim((string) $request->request->get('identifier', ''));
        if ($identifier === '') {
            $this->addFlash('error', 'Email is required.');

            return $this->redirectToRoute('app_login');
        }

        /** @var User|null $user */
        $user = $entityManager->getRepository(User::class)->findOneBy(['email' => $identifier]);

        // Avoid account enumeration: don't reveal whether email exists.
        $verificationUrl = null;
        if ($user instanceof User && $emailVerificationService->needsVerification($user)) {
            $user->setVerificationToken($emailVerificationService->generateVerificationToken());
            $entityManager->flush();

            $verificationUrl = $this->generateUrl(
                'app_verify_email',
                ['token' => $user->getVerificationToken()],
                UrlGeneratorInterface::ABSOLUTE_URL
            );

            try {
                $emailVerificationService->sendVerificationEmail($user, $verificationUrl);
            } catch (\Exception $e) {
                $logger->error('Failed to resend verification email', [
                    'userId' => $user->getId(),
                    'email' => $user->getEmail(),
                    'exception' => $e,
                ]);
                $this->addFlash('error', 'We could not send the verification email. Please try again later or contact support.');
                if ($this->shouldExposeVerificationLink() && $verificationUrl) {
                    $this->addFlash('info', 'Development: Use this verification link: ' . $verificationUrl);
                }

                return $this->redirectToRoute('app_login');
            }
        }

        $this->addFlash('success', 'If an unverified account exists for that email, we sent a verification link. Check your inbox and spam folder.');
        if ($this->shouldExposeVerificationLink() && $verificationUrl) {
            $hint = str_contains($this->mailerDsn, 'mailpit')
                ? 'Local mail: open http://localhost:8025 (Mailpit), or use this link: '
                : 'Development: Verification link: ';
            $this->addFlash('info', $hint . $verificationUrl);
        }

        return $this->redirectToRoute('app_login');
    }
}