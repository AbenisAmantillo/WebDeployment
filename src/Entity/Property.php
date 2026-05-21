<?php

namespace App\Entity;

use App\Repository\PropertyRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
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
    operations: [
        new Get(),
        new GetCollection(),
        new Post(security: "is_granted('ROLE_USER') or is_granted('ROLE_ADMIN') or is_granted('ROLE_STAFF')"),
        new Put(security: "is_granted('ROLE_ADMIN') or is_granted('ROLE_STAFF')"),
        new Patch(security: "is_granted('ROLE_ADMIN') or is_granted('ROLE_STAFF')"),
        new Delete(security: "is_granted('ROLE_ADMIN') or is_granted('ROLE_STAFF')"),
    ],
    normalizationContext: [
        'groups' => ['property:read']
    ],
    denormalizationContext: [
        'groups' => ['property:write']
    ]
)]

#[ORM\Entity(repositoryClass: PropertyRepository::class)]
class Property
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['property:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Groups(['property:read', 'property:write'])]
    private ?string $title = null;

    #[ORM\Column(type: Types::TEXT)]
    #[Groups(['property:read', 'property:write'])]
    private ?string $status = null;

    #[ORM\Column]
    #[Groups(['property:read', 'property:write'])]
    private ?float $price = null;

    #[ORM\Column(length: 255)]
    #[Groups(['property:read', 'property:write'])]
    private ?string $address = null;

    #[ORM\Column(length: 255)]
    #[Groups(['property:read', 'property:write'])]
    private ?string $imageFileName = null;

    /**
     * @var Collection<int, Furniture>
     */
    #[ORM\OneToMany(targetEntity: Furniture::class, mappedBy: 'property', orphanRemoval: true)]
    private Collection $furniture;

    /**
     * @var Collection<int, Transaction>
     */
    #[ORM\OneToMany(targetEntity: Transaction::class, mappedBy: 'property')]
    private Collection $transactions;

    public function __construct()
    {
        $this->furniture = new ArrayCollection();
        $this->transactions = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;

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

    public function getPrice(): ?float
    {
        return $this->price;
    }

    public function setPrice(float $price): static
    {
        $this->price = $price;

        return $this;
    }

    public function getAddress(): ?string
    {
        return $this->address;
    }

    public function setAddress(string $address): static
    {
        $this->address = $address;

        return $this;
    }

    public function getImageFileName(): ?string
    {
        return $this->imageFileName;
    }

    public function setImageFileName(string $imageFileName): static
    {
        $this->imageFileName = $imageFileName;

        return $this;
    }

    /**
     * @return Collection<int, Furniture>
     */
    public function getFurniture(): Collection
    {
        return $this->furniture;
    }

    public function addFurniture(Furniture $furniture): static
    {
        if (!$this->furniture->contains($furniture)) {
            $this->furniture->add($furniture);
            $furniture->setProperty($this);
        }

        return $this;
    }

    public function removeFurniture(Furniture $furniture): static
    {
        if ($this->furniture->removeElement($furniture)) {
            // set the owning side to null (unless already changed)
            if ($furniture->getProperty() === $this) {
                $furniture->setProperty(null);
            }
        }

        return $this;
    }

    public function getType(): string
    {
        return 'Property';
    }

    /**
     * @return Collection<int, Transaction>
     */
    public function getTransactions(): Collection
    {
        return $this->transactions;
    }

    public function addTransaction(Transaction $transaction): static
    {
        if (!$this->transactions->contains($transaction)) {
            $this->transactions->add($transaction);
            $transaction->setProperty($this);
        }

        return $this;
    }

    public function removeTransaction(Transaction $transaction): static
    {
        if ($this->transactions->removeElement($transaction)) {
            // set the owning side to null (unless already changed)
            if ($transaction->getProperty() === $this) {
                $transaction->setProperty(null);
            }
        }

        return $this;
    }
}