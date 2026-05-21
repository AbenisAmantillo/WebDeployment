<?php

namespace App\Entity;

use App\Repository\FurnitureRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\Validator\Constraints as Assert;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Delete;
use Symfony\Component\Serializer\Annotation\Groups;

#[ApiResource(
    shortName: 'furniture',
    operations: [
        new Get(),
        new GetCollection(),
        new Post(security: "is_granted('ROLE_USER') or is_granted('ROLE_ADMIN') or is_granted('ROLE_STAFF')"),
        new Put(security: "is_granted('ROLE_ADMIN') or is_granted('ROLE_STAFF')"),
        new Patch(security: "is_granted('ROLE_ADMIN') or is_granted('ROLE_STAFF')"),
        new Delete(security: "is_granted('ROLE_ADMIN') or is_granted('ROLE_STAFF')"),
    ],
    normalizationContext: [
        'groups' => ['furniture:read']
    ],
    denormalizationContext: [
        'groups' => ['furniture:write']
    ]
)]

#[ORM\Entity(repositoryClass: FurnitureRepository::class)]
class Furniture
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['furniture:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Groups(['furniture:read', 'furniture:write', 'transaction:read'])]
    private ?string $name = null;

    #[ORM\Column]
    #[Groups(['furniture:read', 'furniture:write'])]
    private ?float $price = null;

    #[ORM\Column(length: 255)]
    #[Groups(['furniture:read', 'furniture:write'])]
    private ?string $status = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['furniture:read', 'furniture:write'])]
    private ?int $stock = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    #[Groups(['furniture:read', 'furniture:write', 'transaction:read'])]
    private ?string $image = null;

        #[Assert\Image(
        maxSize: '5M',
        mimeTypes: ['image/jpeg', 'image/png', 'image/gif', 'image/webp'],
        mimeTypesMessage: 'Please upload a valid image (JPEG, PNG, GIF, or WebP)'
    )]
    private ?File $imageFile = null;

        #[ORM\ManyToOne(inversedBy: 'furniture')]
        #[ORM\JoinColumn(nullable: true)]
        private ?Property $property = null;

    public function __construct()
    {
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getPrice(): ?float
    {
        return $this->price;
    }

    public function setPrice(float $price): static
    {
        $this->price = $price;

        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getStock(): ?int
    {
        return $this->stock;
    }

    public function setStock(?int $stock): static
    {
        $this->stock = $stock;

        return $this;
    }

        public function getImage(): ?string
    {
        return $this->image;
    }

    public function setImage(?string $image): self
    {
        $this->image = $image;
        return $this;
    }

    public function getImageFile(): ?File
    {
        return $this->imageFile;
    }

    public function setImageFile(?File $imageFile): self
    {
        $this->imageFile = $imageFile;
        return $this;
    }

    public function getProperty(): ?Property
    {
        return $this->property;
    }

    public function setProperty(?Property $property): static
    {
        $this->property = $property;

        return $this;
    }

    public function getType(): string
    {
        return 'Furniture';
    }

}