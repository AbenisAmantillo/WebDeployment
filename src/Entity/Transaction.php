<?php

namespace App\Entity;

use App\Repository\TransactionRepository;
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
use ApiPlatform\Metadata\Delete;
use Symfony\Component\Serializer\Annotation\Groups;

#[ApiResource(
    operations: [
        new Get(),
        new GetCollection(),
        new Post(security: "is_granted('ROLE_USER') or is_granted('ROLE_ADMIN') or is_granted('ROLE_STAFF')"),
        new Put(security: "is_granted('ROLE_ADMIN') or is_granted('ROLE_STAFF')"),
        new Delete(security: "is_granted('ROLE_ADMIN') or is_granted('ROLE_STAFF')"),
    ],
    normalizationContext: [
        'groups' => ['transaction:read']
    ],
    denormalizationContext: [
        'groups' => ['transaction:write']
    ],
    order: ['date' => 'DESC'],
)]

#[ORM\Entity(repositoryClass: TransactionRepository::class)]
class Transaction
{
    public const PAYMENT_PLAN_MONTHS_OPTIONS = [12, 24, 36];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['transaction:read'])]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['transaction:read', 'transaction:write'])]
    private ?User $customer = null;

    #[ORM\ManyToOne(targetEntity: Property::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['transaction:read', 'transaction:write'])]
    private ?Property $property = null;

    #[ORM\Column(length: 255)]
    #[Groups(['transaction:read', 'transaction:write'])]
    private ?string $purchaseType = null;

    #[ORM\Column]
    #[Groups(['transaction:read', 'transaction:write'])]
    private ?float $price = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Groups(['transaction:read', 'transaction:write'])]    
    private ?\DateTimeInterface $date = null;

    /** Client-submitted downpayment (rent) or full amount hint; cleared when staff receives payment. */
    #[ORM\Column(nullable: true)]
    #[Groups(['transaction:read', 'transaction:write'])]
    private ?float $clientDownpaymentAmount = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['transaction:read', 'transaction:write'])]
    private ?int $clientPaymentPlanMonths = null;

    /** Confirmed installment plan (12, 24, or 36 months) after payment is recorded. */
    #[ORM\Column(nullable: true)]
    #[Groups(['transaction:read', 'transaction:write'])]
    private ?int $paymentPlanMonths = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['transaction:read', 'transaction:write'])]
    private ?string $clientPaymentMethod = null;

    /**
     * Client-submitted furniture selection. This is not persisted directly;
     * the API processor validates it and creates TransactionFurniture rows.
     *
     * @var list<array<string, mixed>>
     */
    #[Groups(['transaction:write'])]
    private array $selectedFurnitureLines = [];

    /**
     * @var Collection<int, Payment>
     */
    #[ORM\OneToMany(targetEntity: Payment::class, mappedBy: 'transaction')]
    private Collection $payments;

    /**
     * @var Collection<int, \App\Entity\TransactionFurniture>
     */
    #[ORM\OneToMany(targetEntity: \App\Entity\TransactionFurniture::class, mappedBy: 'transaction', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $transactionFurniture;

    public function __construct()
    {
        $this->payments = new ArrayCollection();
        $this->transactionFurniture = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getOrderId(): ?int
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

    public function getProperty(): ?Property
    {
        return $this->property;
    }

    public function setProperty(?Property $property): static
    {
        $this->property = $property;

        return $this;
    }

    public function getPropertyName(): ?string
    {
        return $this->property?->getTitle();
    }

    public function getPurchaseType(): ?string
    {
        return $this->purchaseType;
    }

    public function setPurchaseType(string $purchaseType): static
    {
        $this->purchaseType = $purchaseType;

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

    public function getDate(): ?\DateTimeInterface
    {
        return $this->date;
    }

    public function setDate(\DateTimeInterface $date): static
    {
        $this->date = $date;

        return $this;
    }

    public function getClientDownpaymentAmount(): ?float
    {
        return $this->clientDownpaymentAmount;
    }

    public function setClientDownpaymentAmount(?float $clientDownpaymentAmount): static
    {
        $this->clientDownpaymentAmount = $clientDownpaymentAmount;

        return $this;
    }

    public function getClientPaymentPlanMonths(): ?int
    {
        return $this->clientPaymentPlanMonths;
    }

    public function setClientPaymentPlanMonths(?int $clientPaymentPlanMonths): static
    {
        $this->clientPaymentPlanMonths = $clientPaymentPlanMonths;

        return $this;
    }

    public function getPaymentPlanMonths(): ?int
    {
        return $this->paymentPlanMonths;
    }

    public function setPaymentPlanMonths(?int $paymentPlanMonths): static
    {
        $this->paymentPlanMonths = $paymentPlanMonths;

        return $this;
    }

    public static function isValidPaymentPlanMonths(int $months): bool
    {
        return in_array($months, self::PAYMENT_PLAN_MONTHS_OPTIONS, true);
    }

    /**
     * Plan length for display: stored value, client submission, or inferred from installment rows.
     */
    public function getResolvedPaymentPlanMonths(): ?int
    {
        if ($this->paymentPlanMonths !== null && $this->paymentPlanMonths > 0) {
            return $this->paymentPlanMonths;
        }

        if ($this->clientPaymentPlanMonths !== null && $this->clientPaymentPlanMonths > 0) {
            return $this->clientPaymentPlanMonths;
        }

        if (!$this->isRentPurchase() || $this->payments->isEmpty()) {
            return null;
        }

        $payments = $this->payments->toArray();
        usort($payments, static function (Payment $a, Payment $b): int {
            $dateA = $a->getDate();
            $dateB = $b->getDate();

            return ($dateA?->getTimestamp() ?? 0) <=> ($dateB?->getTimestamp() ?? 0);
        });

        $installmentCount = 0;
        $skippedDownpayment = false;

        foreach ($payments as $payment) {
            $status = strtolower(trim((string) $payment->getStatus()));

            if ($status === 'pending') {
                $installmentCount++;
                continue;
            }

            if ($status === 'completed') {
                if (!$skippedDownpayment) {
                    $skippedDownpayment = true;
                    continue;
                }

                $installmentCount++;
            }
        }

        return $installmentCount > 0 ? $installmentCount : null;
    }

    public function countPendingInstallments(): int
    {
        $count = 0;

        foreach ($this->payments as $payment) {
            if ($this->isOutstandingInstallmentStatus($payment)) {
                $count++;
            }
        }

        return $count;
    }

    public function getNextPendingPayment(): ?Payment
    {
        $pending = array_values(array_filter(
            $this->payments->toArray(),
            fn (Payment $payment) => $this->isOutstandingInstallmentStatus($payment)
        ));

        usort($pending, static function (Payment $a, Payment $b): int {
            $dateA = $a->getDate();
            $dateB = $b->getDate();

            return ($dateA?->getTimestamp() ?? 0) <=> ($dateB?->getTimestamp() ?? 0);
        });

        return $pending[0] ?? null;
    }

    public function getClientPaymentMethod(): ?string
    {
        return $this->clientPaymentMethod;
    }

    public function setClientPaymentMethod(?string $clientPaymentMethod): static
    {
        $this->clientPaymentMethod = $clientPaymentMethod;

        return $this;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getSelectedFurnitureLines(): array
    {
        return $this->selectedFurnitureLines;
    }

    /**
     * @param list<array<string, mixed>> $selectedFurnitureLines
     */
    public function setSelectedFurnitureLines(array $selectedFurnitureLines): static
    {
        $this->selectedFurnitureLines = $selectedFurnitureLines;

        return $this;
    }

    /**
     * Alias accepted by mobile clients.
     *
     * @param list<array<string, mixed>> $furnitureLines
     */
    #[Groups(['transaction:write'])]
    public function setFurnitureLines(array $furnitureLines): static
    {
        return $this->setSelectedFurnitureLines($furnitureLines);
    }

    /**
     * True when the client submitted payment details in the app and staff has not confirmed yet.
     */
    public function hasClientPaymentSubmission(): bool
    {
        if ($this->clientPaymentMethod === null || $this->clientPaymentMethod === '') {
            return false;
        }

        if (strtolower((string) $this->purchaseType) === 'rent') {
            return $this->clientDownpaymentAmount !== null
                && $this->clientPaymentPlanMonths !== null
                && $this->clientPaymentPlanMonths > 0;
        }

        return true;
    }

    public function isRentPurchase(): bool
    {
        return strtolower((string) $this->purchaseType) === 'rent';
    }

    public function hasPendingInstallments(): bool
    {
        foreach ($this->payments as $payment) {
            if ($this->isOutstandingInstallmentStatus($payment)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Client-facing payment status (downpayment only ≠ fully paid on installment plans).
     */
    public function getClientPaymentStatus(): string
    {
        if ($this->payments->isEmpty()) {
            return $this->hasClientPaymentSubmission() ? 'awaiting_staff' : 'unpaid';
        }

        if ($this->isFullyPaid()) {
            return 'fully_paid';
        }

        if ($this->isRentPurchase() && $this->hasPendingInstallments()) {
            return 'installments_active';
        }

        return 'in_progress';
    }

    /**
     * Payment values when staff confirms — only from what the client submitted in the app.
     *
     * @return array{is_rent: bool, downpayment: float, payment_plan_months: int, payment_method: string}|null
     */
    public function getStaffReceivePaymentDetails(): ?array
    {
        $isRent = $this->isRentPurchase();
        $paymentMethod = $this->clientPaymentMethod;

        if ($paymentMethod === null || $paymentMethod === '') {
            return null;
        }

        if ($isRent) {
            if ($this->clientDownpaymentAmount === null || $this->clientDownpaymentAmount < 0) {
                return null;
            }

            $paymentPlanMonths = (int) ($this->clientPaymentPlanMonths ?? 0);
            if ($paymentPlanMonths <= 0) {
                return null;
            }

            return [
                'is_rent' => true,
                'downpayment' => (float) $this->clientDownpaymentAmount,
                'payment_plan_months' => $paymentPlanMonths,
                'payment_method' => $paymentMethod,
            ];
        }

        return [
            'is_rent' => false,
            'downpayment' => (float) ($this->price ?? 0),
            'payment_plan_months' => 0,
            'payment_method' => $paymentMethod,
        ];
    }

    /**
     * @return Collection<int, Payment>
     */
    #[Groups(['transaction:read'])]
    public function getPayments(): Collection
    {
        return $this->payments;
    }

    public function addPayment(Payment $payment): static
    {
        if (!$this->payments->contains($payment)) {
            $this->payments->add($payment);
            $payment->setTransaction($this);
        }

        return $this;
    }

    public function removePayment(Payment $payment): static
    {
        if ($this->payments->removeElement($payment)) {
            // set the owning side to null (unless already changed)
            if ($payment->getTransaction() === $this) {
                $payment->setTransaction(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, \App\Entity\TransactionFurniture>
     */
    #[Groups(['transaction:read'])]
    public function getTransactionFurniture(): Collection
    {
        return $this->transactionFurniture;
    }

    public function addTransactionFurniture(\App\Entity\TransactionFurniture $transactionFurniture): static
    {
        if (!$this->transactionFurniture->contains($transactionFurniture)) {
            $this->transactionFurniture->add($transactionFurniture);
            $transactionFurniture->setTransaction($this);
        }

        return $this;
    }

    public function removeTransactionFurniture(\App\Entity\TransactionFurniture $transactionFurniture): static
    {
        if ($this->transactionFurniture->removeElement($transactionFurniture)) {
            if ($transactionFurniture->getTransaction() === $this) {
                $transactionFurniture->setTransaction(null);
            }
        }

        return $this;
    }

    /**
     * Helper method to get furniture items (for backward compatibility in templates)
     */
    public function getFurniture(): array
    {
        $furniture = [];
        foreach ($this->transactionFurniture as $tf) {
            $furniture[] = $tf->getFurniture();
        }
        return $furniture;
    }

    /**
     * True only when every installment is completed and the total paid meets the transaction price.
     */
    #[Groups(['transaction:read'])]
    public function isFullyPaid(): bool
    {
        if ($this->payments->isEmpty()) {
            return false;
        }

        if ($this->hasPendingInstallments()) {
            return false;
        }

        $totalPrice = (float) ($this->price ?? 0);
        $totalPaid = 0.0;

        foreach ($this->payments as $payment) {
            if (strtolower(trim((string) $payment->getStatus())) === 'completed') {
                $totalPaid += (float) ($payment->getAmount() ?? 0);
            }
        }

        if ($totalPrice <= 0) {
            return true;
        }

        return $totalPaid >= ($totalPrice - 0.01);
    }

    #[Groups(['transaction:read'])]
    public function getPaidAmount(): float
    {
        $totalPaid = 0.0;

        foreach ($this->payments as $payment) {
            if (strtolower(trim((string) $payment->getStatus())) === 'completed') {
                $totalPaid += (float) ($payment->getAmount() ?? 0);
            }
        }

        return $totalPaid;
    }

    #[Groups(['transaction:read'])]
    public function getOutstandingBalance(): float
    {
        $totalPrice = (float) ($this->price ?? 0);

        return max(0.0, $totalPrice - $this->getPaidAmount());
    }

    private function isOutstandingInstallmentStatus(Payment $payment): bool
    {
        return in_array(strtolower(trim((string) $payment->getStatus())), ['pending', 'submitted'], true);
    }
}

