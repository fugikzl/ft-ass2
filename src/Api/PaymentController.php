<?php

declare(strict_types=1);

namespace App\Api;

use App\Entity\Payment;
use App\Entity\PaymentAttempt;
use App\Infrastructure\PaymentQueue;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Attributes as OA;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

#[OA\Schema(
    schema: 'Payment',
    type: 'object',
    properties: [
        new OA\Property(property: 'id', type: 'string', example: 'T001'),
        new OA\Property(property: 'amount', type: 'integer', example: 1200),
        new OA\Property(property: 'currency', type: 'string', example: 'KZT'),
        new OA\Property(property: 'failureType', type: 'string', enum: ['None', 'Network', 'Timeout', 'Database']),
        new OA\Property(property: 'status', type: 'string', enum: ['QUEUED', 'PROCESSING', 'FAILED', 'SUCCEEDED', 'ROLLED_BACK']),
        new OA\Property(property: 'attemptCount', type: 'integer', example: 1),
        new OA\Property(property: 'errorType', type: 'string', nullable: true, example: 'Network'),
        new OA\Property(property: 'attempts', type: 'array', items: new OA\Items(ref: '#/components/schemas/Attempt')),
        new OA\Property(property: 'createdAt', type: 'string', format: 'date-time'),
        new OA\Property(property: 'updatedAt', type: 'string', format: 'date-time'),
    ]
)]
#[OA\Schema(
    schema: 'Attempt',
    type: 'object',
    properties: [
        new OA\Property(property: 'number', type: 'integer', example: 1),
        new OA\Property(property: 'outcome', type: 'string', enum: ['FAILED', 'SUCCEEDED']),
        new OA\Property(property: 'errorType', type: 'string', nullable: true, example: 'Network'),
        new OA\Property(property: 'createdAt', type: 'string', format: 'date-time'),
    ]
)]
final class PaymentController
{
    private const FAILURE_TYPES = ['None', 'Network', 'Timeout', 'Database'];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly PaymentQueue $queue
    ) {
    }

    #[OA\Post(
        path: '/api/payments',
        summary: 'Submit and queue a synthetic payment',
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
            required: ['id', 'amount', 'failureType'],
            properties: [
                new OA\Property(property: 'id', type: 'string', example: 'T001'),
                new OA\Property(property: 'amount', type: 'integer', minimum: 1, example: 1200),
                new OA\Property(property: 'failureType', type: 'string', enum: ['None', 'Network', 'Timeout', 'Database']),
            ]
        )),
        responses: [
            new OA\Response(response: 202, description: 'Payment attempt queued', content: new OA\JsonContent(ref: '#/components/schemas/Payment')),
            new OA\Response(response: 409, description: 'Payment ID already exists'),
            new OA\Response(response: 422, description: 'Invalid payment input'),
            new OA\Response(response: 503, description: 'Payment queue is unavailable'),
        ]
    )]
    public function create(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $data = $request->getParsedBody();
        if (!is_array($data)) {
            return $this->error($response, 422, 'validation_error', 'A JSON object is required.');
        }

        $id = $data['id'] ?? null;
        $amount = $data['amount'] ?? null;
        $failureType = $data['failureType'] ?? null;
        if (!is_string($id) || trim($id) === '' || strlen($id) > 64) {
            return $this->error($response, 422, 'validation_error', 'id must be a non-empty string of at most 64 characters.');
        }
        if (!is_int($amount) || $amount <= 0) {
            return $this->error($response, 422, 'validation_error', 'amount must be a positive integer in KZT.');
        }
        if (!is_string($failureType) || !in_array($failureType, self::FAILURE_TYPES, true)) {
            return $this->error($response, 422, 'validation_error', 'failureType must be None, Network, Timeout, or Database.');
        }

        $payment = new Payment(trim($id), $amount, $failureType);
        $this->entityManager->persist($payment);
        try {
            $this->entityManager->flush();
        } catch (UniqueConstraintViolationException) {
            $this->entityManager->clear();
            return $this->error($response, 409, 'duplicate_payment', 'A payment with this ID already exists.');
        }

        try {
            $this->queue->enqueue($payment->getId(), 1);
        } catch (\Throwable) {
            $this->entityManager->remove($payment);
            $this->entityManager->flush();
            return $this->error($response, 503, 'queue_unavailable', 'The payment could not be queued. Please retry the request.');
        }

        return $this->json($response, 202, $this->serializePayment($payment));
    }

    #[OA\Delete(
        path: '/api/payments',
        summary: 'Clear all synthetic payments, attempt logs, and pending payment jobs',
        responses: [
            new OA\Response(response: 200, description: 'Application payment data cleared'),
            new OA\Response(response: 503, description: 'Reset failed'),
        ]
    )]
    public function clearAll(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $connection = $this->entityManager->getConnection();
        $connection->beginTransaction();
        try {
            $attemptsDeleted = $connection->executeStatement('DELETE FROM payment_attempts');
            $paymentsDeleted = $connection->executeStatement('DELETE FROM payments');
            $this->queue->clear();
            $connection->commit();
            $this->entityManager->clear();
        } catch (\Throwable) {
            if ($connection->isTransactionActive()) {
                $connection->rollBack();
            }
            return $this->error($response, 503, 'clear_failed', 'Payment data could not be cleared.');
        }

        return $this->json($response, 200, [
            'paymentsDeleted' => $paymentsDeleted,
            'attemptsDeleted' => $attemptsDeleted,
        ]);
    }

    #[OA\Get(
        path: '/api/payments/{id}',
        summary: 'Retrieve payment status and attempt logs',
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string'))],
        responses: [
            new OA\Response(response: 200, description: 'Payment status', content: new OA\JsonContent(ref: '#/components/schemas/Payment')),
            new OA\Response(response: 404, description: 'Payment not found'),
        ]
    )]
    public function show(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $payment = $this->entityManager->find(Payment::class, $args['id']);
        if (!$payment instanceof Payment) {
            return $this->error($response, 404, 'payment_not_found', 'No payment exists with this ID.');
        }

        return $this->json($response, 200, $this->serializePayment($payment));
    }

    #[OA\Post(
        path: '/api/payments/{id}/retry',
        summary: 'Queue the next attempt after a failed attempt',
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string'))],
        responses: [
            new OA\Response(response: 202, description: 'Retry attempt queued', content: new OA\JsonContent(ref: '#/components/schemas/Payment')),
            new OA\Response(response: 404, description: 'Payment not found'),
            new OA\Response(response: 409, description: 'Payment is not eligible for retry'),
            new OA\Response(response: 503, description: 'Payment queue is unavailable'),
        ]
    )]
    public function retry(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $payment = $this->entityManager->find(Payment::class, $args['id']);
        if (!$payment instanceof Payment) {
            return $this->error($response, 404, 'payment_not_found', 'No payment exists with this ID.');
        }
        if ($payment->getAttemptCount() >= 3) {
            return $this->error($response, 409, 'retry_limit_reached', 'A payment may have at most three attempts.');
        }
        if ($payment->getStatus() !== Payment::STATUS_FAILED) {
            return $this->error($response, 409, 'retry_not_allowed', 'A retry is allowed only after a failed attempt.');
        }

        $nextAttempt = $payment->getAttemptCount() + 1;
        $payment->setStatus(Payment::STATUS_QUEUED);
        $this->entityManager->flush();
        try {
            $this->queue->enqueue($payment->getId(), $nextAttempt);
        } catch (\Throwable) {
            $payment->setStatus(Payment::STATUS_FAILED);
            $this->entityManager->flush();
            return $this->error($response, 503, 'queue_unavailable', 'The retry could not be queued. Please request it again.');
        }

        return $this->json($response, 202, $this->serializePayment($payment));
    }

    public function ping(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        return $this->json($response, 200, ['status' => 'ok']);
    }

    private function serializePayment(Payment $payment): array
    {
        $attempts = $this->entityManager->getRepository(PaymentAttempt::class)->findBy(
            ['payment' => $payment],
            ['number' => 'ASC']
        );

        return [
            'id' => $payment->getId(),
            'amount' => $payment->getAmount(),
            'currency' => 'KZT',
            'failureType' => $payment->getFailureType(),
            'status' => $payment->getStatus(),
            'attemptCount' => $payment->getAttemptCount(),
            'errorType' => $payment->getLatestErrorType(),
            'attempts' => array_map(static fn (PaymentAttempt $attempt): array => [
                'number' => $attempt->getNumber(),
                'outcome' => $attempt->getOutcome(),
                'errorType' => $attempt->getErrorType(),
                'createdAt' => $attempt->getCreatedAt()->format(DATE_ATOM),
            ], $attempts),
            'createdAt' => $payment->getCreatedAt()->format(DATE_ATOM),
            'updatedAt' => $payment->getUpdatedAt()->format(DATE_ATOM),
        ];
    }

    private function error(ResponseInterface $response, int $status, string $type, string $message): ResponseInterface
    {
        return $this->json($response, $status, ['error' => ['type' => $type, 'message' => $message]]);
    }

    private function json(ResponseInterface $response, int $status, array $data): ResponseInterface
    {
        $response->getBody()->write(json_encode($data, JSON_THROW_ON_ERROR));
        return $response->withStatus($status)->withHeader('Content-Type', 'application/json');
    }
}
