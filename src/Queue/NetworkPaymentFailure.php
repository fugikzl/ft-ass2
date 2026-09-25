<?php

declare(strict_types=1);

namespace App\Queue;

final class NetworkPaymentFailure extends SimulatedPaymentFailure
{
    public function __construct()
    {
        parent::__construct('Network');
    }
}
