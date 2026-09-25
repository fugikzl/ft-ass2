<?php

declare(strict_types=1);

namespace App\Queue;

abstract class SimulatedPaymentFailure extends \RuntimeException
{
    protected function __construct(public readonly string $errorType)
    {
        parent::__construct('Synthetic ' . $errorType . ' failure.');
    }
}
