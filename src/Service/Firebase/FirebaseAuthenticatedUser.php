<?php

namespace App\Service\Firebase;

final readonly class FirebaseAuthenticatedUser
{
    public function __construct(
        public string $uid,
        public string $email,
        public ?string $displayName,
        public ?bool $emailVerified,
    ) {
    }
}
