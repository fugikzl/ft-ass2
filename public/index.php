<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use App\Api\PaymentController;
use App\Infrastructure\Database;
use App\Infrastructure\PaymentQueue;
use Slim\Factory\AppFactory;

$entityManager = Database::createEntityManager();
$queue = PaymentQueue::create();
$controller = new PaymentController($entityManager, $queue);

$app = AppFactory::create();
$app->addBodyParsingMiddleware();
$app->addRoutingMiddleware();
$app->addErrorMiddleware(
    filter_var(getenv('APP_DEBUG') ?: false, FILTER_VALIDATE_BOOL),
    true,
    true
);

$app->get('/', [$controller, 'ping']);
$app->post('/api/payments', [$controller, 'create']);
$app->delete('/api/payments', [$controller, 'clearAll']);
$app->get('/api/payments/{id}', [$controller, 'show']);
$app->post('/api/payments/{id}/retry', [$controller, 'retry']);

$app->run();
