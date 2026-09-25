<?php

declare(strict_types=1);

namespace App\Queue;

final class ProcessPaymentAttempt
{
    public function __construct(
        public readonly string $paymentId,
        public readonly int $attemptNumber
    ) {
    }
}
