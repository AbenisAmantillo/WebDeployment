#!/usr/bin/env php
<?php

use App\Service\Firebase\FirebaseCredentialsResolver;

require dirname(__DIR__).'/vendor/autoload.php';

$resolver = new FirebaseCredentialsResolver();

try {
    $credentialsPath = $resolver->resolveFromEnvironment();
} catch (\Throwable $exception) {
    fwrite(STDERR, $exception->getMessage().PHP_EOL);
    exit(1);
}

if (getenv('FIREBASE_CREDENTIALS_JSON') !== false && trim((string) getenv('FIREBASE_CREDENTIALS_JSON')) !== '') {
    fwrite(STDERR, sprintf('Firebase credentials JSON was written to "%s".%s', $credentialsPath, PHP_EOL));
} else {
    fwrite(STDERR, sprintf('Firebase credentials file "%s" is readable.%s', $credentialsPath, PHP_EOL));
}

fwrite(STDOUT, $credentialsPath);
