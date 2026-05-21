<?php

namespace App\Service\Firebase;

interface FirebaseTokenVerifierInterface
{
    /**
     * @throws InvalidFirebaseTokenException
     * @throws FirebaseTokenMissingEmailException
     */
    public function verify(string $idToken): FirebaseAuthenticatedUser;
}
