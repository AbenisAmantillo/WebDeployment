<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use App\Repository\TransactionFurnitureRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ApiResource(
    operations: [
        new Get(),
        new GetCollection(),
        new Post(security: "is_granted('ROLE_ADMIN') or is_granted('ROLE_STAFF')"),
    ],
    normalizationContext: [
        'groups' => ['transaction_furniture:read'],
    ],
    denormalizationContext: [
        'groups' => ['transaction_furniture:write'],
    ],
)]
#[ORM\Entity(repositoryClass: TransactionFurnitureRepository::class)]
#[ORM\Table(name: 'transaction_furniture')]
class TransactionFurniture
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['transaction_furniture:read', 'transaction:read'])]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Transaction::class, inversedBy: 'transactionFurniture')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['transaction_furniture:read', 'transaction_furniture:write'])]
    private ?Transaction $transaction = null;

    #[ORM\ManyToOne(targetEntity: Furniture::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['transaction_furniture:read', 'transaction_furniture:write', 'transaction:read'])]
    private ?Furniture $furniture = null;

    #[ORM\Column]
    #[Groups(['transaction_furniture:read', 'transaction_furniture:write', 'transaction:read'])]
    private ?int $quantity = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTransaction(): ?Transaction
    {
        return $this->transaction;
    }

    public function setTransaction(?Transaction $transaction): static
    {
        $this->transaction = $transaction;

        return $this;
    }

    public function getFurniture(): ?Furniture
    {
        return $this->furniture;
    }

    public function setFurniture(?Furniture $furniture): static
    {
        $this->furniture = $furniture;

        return $this;
    }

    public function getQuantity(): ?int
    {
        return $this->quantity;
    }

    public function setQuantity(int $quantity): static
    {
        $this->quantity = $quantity;

        return $this;
    }
}
