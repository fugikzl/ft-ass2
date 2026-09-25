<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use App\Infrastructure\Database;
use App\Infrastructure\PaymentQueue;
use App\Queue\ProcessPaymentAttempt;
use App\Queue\ProcessPaymentAttemptHandler;
use Symfony\Component\Messenger\Handler\HandlerDescriptor;
use Symfony\Component\Messenger\Handler\HandlersLocator;
use Symfony\Component\Messenger\Middleware\HandleMessageMiddleware;
use Symfony\Component\Messenger\MessageBus;
use Symfony\Component\Messenger\Worker;

$entityManager = Database::createEntityManager();
$queue = PaymentQueue::create();
$handler = new ProcessPaymentAttemptHandler($entityManager);
$locator = new HandlersLocator([
    ProcessPaymentAttempt::class => [new HandlerDescriptor($handler)],
]);
$bus = new MessageBus([new HandleMessageMiddleware($locator)]);
$worker = new Worker(['payments' => $queue->getTransport()], $bus);

fwrite(STDOUT, "Payment worker started. Waiting for attempts...\n");
$worker->run();
