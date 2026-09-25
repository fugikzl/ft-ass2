<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(
    name: 'payment_attempts',
    uniqueConstraints: [new ORM\UniqueConstraint(name: 'uniq_payment_attempt_number', columns: ['payment_id', 'number'])]
)]
class PaymentAttempt
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Payment::class, inversedBy: 'attempts')]
    #[ORM\JoinColumn(name: 'payment_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private Payment $payment;

    #[ORM\Column(type: 'integer')]
    private int $number;

    #[ORM\Column(type: 'string', length: 16)]
    private string $outcome;

    #[ORM\Column(name: 'error_type', type: 'string', length: 32, nullable: true)]
    private ?string $errorType;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    public function __construct(Payment $payment, int $number, string $outcome, ?string $errorType)
    {
        $this->payment = $payment;
        $this->number = $number;
        $this->outcome = $outcome;
        $this->errorType = $errorType;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getNumber(): int { return $this->number; }
    public function getOutcome(): string { return $this->outcome; }
    public function getErrorType(): ?string { return $this->errorType; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
}
