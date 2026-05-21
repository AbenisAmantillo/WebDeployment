<?php

namespace App\Entity;

use App\Repository\PaymentRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use ApiPlatform\Metadata\Delete;
use Symfony\Component\Serializer\Annotation\Groups;

#[ApiResource(
    operations: [
        new Get(),
        new GetCollection(),
        new Post(security: "is_granted('ROLE_ADMIN') or is_granted('ROLE_STAFF')"),
        new Put(security: "is_granted('ROLE_ADMIN') or is_granted('ROLE_STAFF')"),
        new Delete(security: "is_granted('ROLE_ADMIN') or is_granted('ROLE_STAFF')"),
    ],
    normalizationContext: [
        'groups' => ['payment:read']
    ],
    denormalizationContext: [
        'groups' => ['payment:write']
    ]
)]

#[ORM\Entity(repositoryClass: PaymentRepository::class)]
class Payment
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['payment:read', 'transaction:read'])]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['payment:read', 'payment:write'])]
    private ?User $customer = null;

    #[ORM\ManyToOne(targetEntity: Transaction::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['payment:read', 'payment:write'])]
    private ?Transaction $transaction = null;

    #[ORM\Column]
    #[Groups(['payment:read', 'payment:write', 'transaction:read'])]
    private ?float $amount = null;

    #[ORM\Column(length: 255)]
    #[Groups(['payment:read', 'payment:write', 'transaction:read'])]
    private ?string $paymentMethod = null;

    #[ORM\Column(length: 255)]
    #[Groups(['payment:read', 'payment:write', 'transaction:read'])]
    private ?string $status = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Groups(['payment:read', 'payment:write', 'transaction:read'])]
    private ?\DateTimeInterface $date = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCustomer(): ?User
    {
        return $this->customer;
    }

    public function setCustomer(?User $customer): static
    {
        $this->customer = $customer;

        return $this;
    }

    public function getCustomerName(): ?string
    {
        return $this->customer?->getUsername();
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

    public function getTransactionId(): ?int
    {
        return $this->transaction?->getId();
    }

    public function getAmount(): ?float
    {
        return $this->amount;
    }

    public function setAmount(float $amount): static
    {
        $this->amount = $amount;

        return $this;
    }

    public function getPaymentMethod(): ?string
    {
        return $this->paymentMethod;
    }

    public function setPaymentMethod(string $paymentMethod): static
    {
        $this->paymentMethod = $paymentMethod;

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

    public function getDate(): ?\DateTimeInterface
    {
        return $this->date;
    }

    public function setDate(\DateTimeInterface $date): static
    {
        $this->date = $date;

        return $this;
    }
}

