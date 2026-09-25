<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'payments')]
class Payment
{
    public const STATUS_QUEUED = 'QUEUED';
    public const STATUS_PROCESSING = 'PROCESSING';
    public const STATUS_FAILED = 'FAILED';
    public const STATUS_SUCCEEDED = 'SUCCEEDED';
    public const STATUS_ROLLED_BACK = 'ROLLED_BACK';

    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 64)]
    private string $id;

    #[ORM\Column(type: 'integer')]
    private int $amount;

    #[ORM\Column(name: 'failure_type', type: 'string', length: 32)]
    private string $failureType;

    #[ORM\Column(type: 'string', length: 24)]
    private string $status = self::STATUS_QUEUED;

    #[ORM\Column(name: 'attempt_count', type: 'integer')]
    private int $attemptCount = 0;

    #[ORM\Column(name: 'latest_error_type', type: 'string', length: 32, nullable: true)]
    private ?string $latestErrorType = null;

    #[ORM\Column(name: 'processed_at', type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $processedAt = null;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'updated_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    /** @var Collection<int, PaymentAttempt> */
    #[ORM\OneToMany(mappedBy: 'payment', targetEntity: PaymentAttempt::class)]
    #[ORM\OrderBy(['number' => 'ASC'])]
    private Collection $attempts;

    public function __construct(string $id, int $amount, string $failureType)
    {
        $this->id = $id;
        $this->amount = $amount;
        $this->failureType = $failureType;
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = $this->createdAt;
        $this->attempts = new ArrayCollection();
    }

    public function getId(): string { return $this->id; }
    public function getAmount(): int { return $this->amount; }
    public function getFailureType(): string { return $this->failureType; }
    public function getStatus(): string { return $this->status; }
    public function getAttemptCount(): int { return $this->attemptCount; }
    public function getLatestErrorType(): ?string { return $this->latestErrorType; }
    public function getProcessedAt(): ?\DateTimeImmutable { return $this->processedAt; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getUpdatedAt(): \DateTimeImmutable { return $this->updatedAt; }

    /** @return Collection<int, PaymentAttempt> */
    public function getAttempts(): Collection { return $this->attempts; }

    public function setStatus(string $status): void
    {
        $this->status = $status;
        $this->touch();
    }

    public function completeAttempt(int $number, ?string $errorType): void
    {
        $this->attemptCount = $number;
        $this->latestErrorType = $errorType;
        $this->touch();
    }

    private function touch(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }
}
