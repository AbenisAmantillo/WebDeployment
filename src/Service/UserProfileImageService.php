<?php

namespace App\Service;

use App\Entity\User;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\String\Slugger\SluggerInterface;

class UserProfileImageService
{
    private const ALLOWED_MIME_TYPES = [
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',
    ];

    public function __construct(
        private readonly SluggerInterface $slugger,
        #[Autowire(param: 'user_profile_images_directory')]
        private readonly string $profileImagesDirectory,
    ) {
    }

    /**
     * @throws \InvalidArgumentException
     * @throws FileException
     */
    public function store(UploadedFile $file): string
    {
        if (!\in_array($file->getMimeType(), self::ALLOWED_MIME_TYPES, true)) {
            throw new \InvalidArgumentException('Please upload a valid image (JPEG, PNG, GIF, or WebP).');
        }

        $originalFilename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $safeFilename = $this->slugger->slug($originalFilename);
        $newFilename = $safeFilename.'-'.uniqid().'.'.$file->guessExtension();

        $file->move($this->profileImagesDirectory, $newFilename);

        return $newFilename;
    }

    public function removeFile(?string $filename): void
    {
        if ($filename === null || $filename === '') {
            return;
        }

        $path = $this->profileImagesDirectory.'/'.$filename;
        if (is_file($path)) {
            @unlink($path);
        }
    }

    public function replaceProfileImage(User $user, UploadedFile $file): void
    {
        $newFilename = $this->store($file);
        $this->removeFile($user->getProfileImageFileName());
        $user->setProfileImageFileName($newFilename);
    }

    public function clearProfileImage(User $user): void
    {
        $this->removeFile($user->getProfileImageFileName());
        $user->setProfileImageFileName(null);
    }
}
