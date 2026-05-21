<?php

namespace App\Service\Firebase;

use Kreait\Firebase\Contract\Auth;
use Kreait\Firebase\Exception\Auth\FailedToVerifyToken;
use Kreait\Firebase\Exception\Auth\RevokedIdToken;

final class KreaitFirebaseTokenVerifier implements FirebaseTokenVerifierInterface
{
    public function __construct(
        private readonly Auth $auth,
    ) {
    }

    public function verify(string $idToken): FirebaseAuthenticatedUser
    {
        try {
            $verifiedToken = $this->auth->verifyIdToken($idToken);
        } catch (FailedToVerifyToken|RevokedIdToken $exception) {
            throw new InvalidFirebaseTokenException('Invalid Firebase ID token.', previous: $exception);
        }

        $claims = $verifiedToken->claims();
        $uid = (string) $claims->get('sub');
        $email = $claims->get('email');

        if (!is_string($email) || trim($email) === '') {
            throw new FirebaseTokenMissingEmailException('Firebase token does not include an email address.');
        }

        $displayName = $claims->get('name') ?? $claims->get('displayName');
        $emailVerified = $claims->get('email_verified');

        return new FirebaseAuthenticatedUser(
            uid: $uid,
            email: trim($email),
            displayName: is_string($displayName) && trim($displayName) !== '' ? trim($displayName) : null,
            emailVerified: is_bool($emailVerified) ? $emailVerified : null,
        );
    }
}
