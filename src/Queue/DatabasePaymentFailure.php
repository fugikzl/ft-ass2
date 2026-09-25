<?php

declare(strict_types=1);

namespace App\Queue;

final class DatabasePaymentFailure extends SimulatedPaymentFailure
{
    public function __construct()
    {
        parent::__construct('Database');
    }
}
