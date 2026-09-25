<?php

declare(strict_types=1);

namespace App\Api;

use OpenApi\Attributes as OA;

#[OA\OpenApi(openapi: '3.0.3')]
#[OA\Info(
    title: 'Synthetic Payment Retry API',
    version: '1.0.0',
    description: 'Educational API for asynchronous payment processing and caller-requested retries.'
)]
#[OA\Server(url: 'http://localhost:8080', description: 'Docker Compose development server')]
final class OpenApiSpec
{
}
