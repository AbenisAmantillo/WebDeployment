<?php

namespace App\Tests\Service\Firebase;

use App\Service\Firebase\FirebaseCredentialsResolver;
use PHPUnit\Framework\TestCase;

final class FirebaseCredentialsResolverTest extends TestCase
{
    private const SERVICE_ACCOUNT_JSON = '{"type":"service_account","project_id":"demo-project","private_key":"-----BEGIN PRIVATE KEY-----\nfake-key\n-----END PRIVATE KEY-----\n","client_email":"firebase-adminsdk@example.iam.gserviceaccount.com"}';

    /** @var list<string> */
    private array $temporaryPaths = [];

    protected function tearDown(): void
    {
        foreach ($this->temporaryPaths as $temporaryPath) {
            if (is_file($temporaryPath)) {
                @unlink($temporaryPath);
            }

            if (is_dir($temporaryPath)) {
                @rmdir($temporaryPath);
            }
        }
    }

    public function testJsonCredentialsTakePrecedenceAndAreWrittenToTempFile(): void
    {
        $targetPath = $this->temporaryPath('firebase-service-account.json');

        $resolvedPath = (new FirebaseCredentialsResolver())->resolve(
            self::SERVICE_ACCOUNT_JSON,
            '/does/not/exist/service-account.json',
            $targetPath,
        );

        self::assertSame($targetPath, $resolvedPath);
        self::assertSame(self::SERVICE_ACCOUNT_JSON, file_get_contents($targetPath));
    }

    public function testExistingCredentialsPathIsUsedWhenJsonIsMissing(): void
    {
        $credentialsPath = $this->temporaryPath('existing-service-account.json');
        mkdir(dirname($credentialsPath), 0700, true);
        file_put_contents($credentialsPath, self::SERVICE_ACCOUNT_JSON);

        $resolvedPath = (new FirebaseCredentialsResolver())->resolve(null, $credentialsPath);

        self::assertSame($credentialsPath, $resolvedPath);
    }

    public function testMissingCredentialsFailClearly(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Set FIREBASE_CREDENTIALS_JSON');

        (new FirebaseCredentialsResolver())->resolve(null, null);
    }

    public function testLiteralKernelProjectDirPlaceholderFailsClearly(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('%kernel.project_dir%');

        (new FirebaseCredentialsResolver())->resolve(null, '%kernel.project_dir%/config/firebase/service-account.json');
    }

    public function testInvalidJsonFailsClearly(): void
    {
        $targetPath = $this->temporaryPath('invalid-service-account.json');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('valid Firebase service account JSON');

        (new FirebaseCredentialsResolver())->resolve('{invalid json', null, $targetPath);
    }

    private function temporaryPath(string $filename): string
    {
        $directory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'firebase-resolver-test-'.bin2hex(random_bytes(6));
        $path = $directory.DIRECTORY_SEPARATOR.$filename;
        $this->temporaryPaths[] = $path;
        $this->temporaryPaths[] = $directory;

        return $path;
    }
}
