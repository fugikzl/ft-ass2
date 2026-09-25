<?php

declare(strict_types=1);

namespace App\Queue;

use App\Entity\Payment;
use App\Entity\PaymentAttempt;
use Doctrine\ORM\EntityManagerInterface;

final class ProcessPaymentAttemptHandler
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    public function __invoke(ProcessPaymentAttempt $message): void
    {
        // A worker handles many messages, while API requests change payment state.
        // Drop the identity map so each attempt sees the latest committed state.
        $this->entityManager->clear();
        $payment = $this->entityManager->find(Payment::class, $message->paymentId);
        if (!$payment instanceof Payment || !in_array(
            $payment->getStatus(),
            [Payment::STATUS_QUEUED, Payment::STATUS_PROCESSING],
            true
        )) {
            return;
        }

        $expectedAttempt = $payment->getAttemptCount() + 1;
        if ($message->attemptNumber !== $expectedAttempt) {
            return;
        }

        $existing = $this->entityManager->getRepository(PaymentAttempt::class)->findOneBy([
            'payment' => $payment,
            'number' => $message->attemptNumber,
        ]);
        if ($existing instanceof PaymentAttempt) {
            return;
        }

        $payment->setStatus(Payment::STATUS_PROCESSING);
        $this->entityManager->flush();

        $failure = $this->failureForAttempt($payment, $message->attemptNumber);
        $errorType = null;
        $connection = $this->entityManager->getConnection();
        $connection->beginTransaction();
        try {
            // The Database scenario writes a processing marker and then fails,
            // so the rollback is visible in the database while the audit log survives.
            if ($failure instanceof DatabasePaymentFailure) {
                $connection->executeStatement(
                    'UPDATE payments SET processed_at = UTC_TIMESTAMP(6) WHERE id = ?',
                    [$payment->getId()]
                );
                throw $failure;
            }

            if ($failure !== null) {
                throw $failure;
            }

            $connection->executeStatement(
                'UPDATE payments SET processed_at = UTC_TIMESTAMP(6) WHERE id = ?',
                [$payment->getId()]
            );
            $connection->commit();
        } catch (SimulatedPaymentFailure $failure) {
            $connection->rollBack();
            $errorType = $failure->errorType;
        } catch (\Throwable $failure) {
            $connection->rollBack();
            throw $failure;
        }

        $outcome = $errorType === null ? 'SUCCEEDED' : 'FAILED';
        $terminalDatabaseFailure = $errorType === 'Database' && $message->attemptNumber >= 3;
        $attempt = new PaymentAttempt($payment, $message->attemptNumber, $outcome, $errorType);
        $this->entityManager->persist($attempt);
        $payment->completeAttempt($message->attemptNumber, $errorType);
        $payment->setStatus(match (true) {
            $outcome === 'SUCCEEDED' => Payment::STATUS_SUCCEEDED,
            $terminalDatabaseFailure => Payment::STATUS_ROLLED_BACK,
            default => Payment::STATUS_FAILED,
        });
        $this->entityManager->flush();
    }

    private function failureForAttempt(Payment $payment, int $attemptNumber): ?SimulatedPaymentFailure
    {
        return match ($payment->getFailureType()) {
            'None' => null,
            'Network' => $attemptNumber === 1 ? new NetworkPaymentFailure() : null,
            'Timeout' => $attemptNumber <= 2 ? new TimeoutPaymentFailure() : null,
            'Database' => new DatabasePaymentFailure(),
            default => throw new \LogicException('Unsupported payment failure type.'),
        };
    }
}
