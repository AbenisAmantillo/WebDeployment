<?php

namespace App\Service\Firebase;

final class FirebaseCredentialsResolver
{
    public const DEFAULT_TEMP_FILE = '/tmp/firebase-service-account.json';

    public function resolveFromEnvironment(string $jsonTargetPath = self::DEFAULT_TEMP_FILE): string
    {
        $credentialsPath = $this->resolve(
            $this->readEnvironmentVariable('FIREBASE_CREDENTIALS_JSON'),
            $this->readEnvironmentVariable('FIREBASE_CREDENTIALS'),
            $jsonTargetPath,
        );

        $_ENV['FIREBASE_CREDENTIALS'] = $credentialsPath;
        $_SERVER['FIREBASE_CREDENTIALS'] = $credentialsPath;
        putenv('FIREBASE_CREDENTIALS='.$credentialsPath);

        return $credentialsPath;
    }

    public function resolve(?string $credentialsJson, ?string $credentialsPath, string $jsonTargetPath = self::DEFAULT_TEMP_FILE): string
    {
        if ($this->hasValue($credentialsJson)) {
            return $this->writeJsonCredentials($credentialsJson, $jsonTargetPath);
        }

        if ($this->hasValue($credentialsPath)) {
            return $this->validateCredentialsPath($credentialsPath);
        }

        throw new \RuntimeException(
            'Firebase Admin credentials are not configured. Set FIREBASE_CREDENTIALS_JSON to the full Firebase service account JSON, or set FIREBASE_CREDENTIALS to a readable service account JSON file path.'
        );
    }

    private function writeJsonCredentials(string $credentialsJson, string $jsonTargetPath): string
    {
        $this->validateServiceAccountJson($credentialsJson);

        $targetDirectory = dirname($jsonTargetPath);
        if (!is_dir($targetDirectory) && !mkdir($targetDirectory, 0700, true) && !is_dir($targetDirectory)) {
            throw new \RuntimeException(sprintf('Could not create Firebase credentials directory "%s".', $targetDirectory));
        }

        $bytesWritten = @file_put_contents($jsonTargetPath, $credentialsJson, LOCK_EX);
        if ($bytesWritten === false) {
            throw new \RuntimeException(sprintf('Could not write Firebase credentials file "%s". Check filesystem permissions.', $jsonTargetPath));
        }

        @chmod($jsonTargetPath, 0600);
        $this->assertReadableFile($jsonTargetPath, 'generated Firebase credentials file');

        $writtenJson = @file_get_contents($jsonTargetPath);
        if ($writtenJson === false) {
            throw new \RuntimeException(sprintf('Could not read generated Firebase credentials file "%s".', $jsonTargetPath));
        }

        $this->validateServiceAccountJson($writtenJson);

        return $jsonTargetPath;
    }

    private function validateCredentialsPath(string $credentialsPath): string
    {
        if (str_contains($credentialsPath, '%kernel.project_dir%')) {
            throw new \RuntimeException(
                'FIREBASE_CREDENTIALS contains the literal Symfony placeholder "%kernel.project_dir%". Use a runtime file path such as "/var/www/html/config/firebase/service-account.json".'
            );
        }

        $this->assertReadableFile($credentialsPath, 'Firebase credentials file');

        return $credentialsPath;
    }

    private function validateServiceAccountJson(string $credentialsJson): void
    {
        $decoded = json_decode($credentialsJson, true);
        if (!is_array($decoded)) {
            throw new \RuntimeException(sprintf('FIREBASE_CREDENTIALS_JSON must contain valid Firebase service account JSON. JSON error: %s.', json_last_error_msg()));
        }

        foreach (['project_id', 'private_key', 'client_email'] as $requiredKey) {
            if (!isset($decoded[$requiredKey]) || !is_string($decoded[$requiredKey]) || trim($decoded[$requiredKey]) === '') {
                throw new \RuntimeException(sprintf('FIREBASE_CREDENTIALS_JSON is missing required service account field "%s".', $requiredKey));
            }
        }
    }

    private function assertReadableFile(string $path, string $description): void
    {
        if (!is_file($path) || !is_readable($path)) {
            throw new \RuntimeException(sprintf('%s "%s" does not exist or is not readable.', $description, $path));
        }
    }

    private function hasValue(?string $value): bool
    {
        return $value !== null && trim($value) !== '';
    }

    private function readEnvironmentVariable(string $name): ?string
    {
        if (array_key_exists($name, $_SERVER)) {
            return (string) $_SERVER[$name];
        }

        if (array_key_exists($name, $_ENV)) {
            return (string) $_ENV[$name];
        }

        $value = getenv($name);

        return $value === false ? null : (string) $value;
    }
}
