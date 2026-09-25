<?php

declare(strict_types=1);

namespace App\Infrastructure;

use App\Queue\ProcessPaymentAttempt;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\TransportInterface;
use Symfony\Component\Messenger\Transport\Serialization\PhpSerializer;
use Symfony\Component\Messenger\Transport\TransportFactory;
use Symfony\Component\Messenger\Bridge\Redis\Transport\RedisTransportFactory;
use Symfony\Component\Messenger\Bridge\Redis\Transport\Connection;

final class PaymentQueue
{
    private function __construct(
        private readonly TransportInterface $transport,
        private readonly string $dsn
    )
    {
    }

    public static function create(): self
    {
        $dsn = getenv('MESSENGER_TRANSPORT_DSN') ?: 'redis://127.0.0.1:6379/payments?auto_setup=true';
        $factory = new TransportFactory([new RedisTransportFactory()]);
        $transport = $factory->createTransport($dsn, [], new PhpSerializer());

        return new self($transport, $dsn);
    }

    public function enqueue(string $paymentId, int $attemptNumber): void
    {
        $this->transport->send(new Envelope(new ProcessPaymentAttempt($paymentId, $attemptNumber)));
    }

    public function getTransport(): TransportInterface
    {
        return $this->transport;
    }

    public function clear(): void
    {
        $connection = Connection::fromDsn($this->dsn, []);
        $connection->cleanup();
        $connection->setup();
    }
}
