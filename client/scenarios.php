<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use GuzzleHttp\Client;

$baseUrl = rtrim(getenv('APP_BASE_URL') ?: 'http://localhost:8080', '/');
$client = new Client(['base_uri' => $baseUrl, 'http_errors' => false, 'timeout' => 5]);
$scenarios = [
    ['id' => 'T001', 'amount' => 12000, 'failureType' => 'None'],
    ['id' => 'T002', 'amount' => 25000, 'failureType' => 'Network'],
    ['id' => 'T003', 'amount' => 8000, 'failureType' => 'None'],
    ['id' => 'T004', 'amount' => 45000, 'failureType' => 'Timeout'],
    ['id' => 'T005', 'amount' => 13000, 'failureType' => 'None'],
    ['id' => 'T006', 'amount' => 70000, 'failureType' => 'Database'],
    ['id' => 'T007', 'amount' => 9000, 'failureType' => 'None'],
    ['id' => 'T008', 'amount' => 31000, 'failureType' => 'Network'],
    ['id' => 'T009', 'amount' => 15000, 'failureType' => 'None'],
    ['id' => 'T010', 'amount' => 50000, 'failureType' => 'Timeout'],
    ['id' => 'T011', 'amount' => 6000, 'failureType' => 'None'],
    ['id' => 'T012', 'amount' => 80000, 'failureType' => 'Database'],
    ['id' => 'T013', 'amount' => 11000, 'failureType' => 'None'],
    ['id' => 'T014', 'amount' => 22000, 'failureType' => 'None'],
    ['id' => 'T015', 'amount' => 40000, 'failureType' => 'Network'],
];

foreach ($scenarios as $scenario) {
    $create = $client->post('/api/payments', ['json' => $scenario]);
    if ($create->getStatusCode() === 202) {
        fwrite(STDOUT, sprintf("%s submitted.\n", $scenario['id']));
    } elseif ($create->getStatusCode() === 409) {
        $existing = getPayment($client, $scenario['id']);
        if ($existing['amount'] !== $scenario['amount'] || $existing['failureType'] !== $scenario['failureType']) {
            throw new RuntimeException(sprintf('Payment ID %s already exists with different scenario data.', $scenario['id']));
        }
        fwrite(STDOUT, sprintf("%s exists with matching scenario data; reusing it.\n", $scenario['id']));
    } else {
        throw new RuntimeException(sprintf('Could not submit %s: HTTP %d %s', $scenario['id'], $create->getStatusCode(), (string) $create->getBody()));
    }
}

foreach ($scenarios as $scenario) {
    $startedAt = microtime(true);
    while (true) {
        $payment = getPayment($client, $scenario['id']);
        if ($payment['status'] === 'FAILED' && $payment['attemptCount'] < 3) {
            fwrite(STDOUT, sprintf("%s failed with %s; requesting retry %d.\n", $scenario['id'], $payment['errorType'], $payment['attemptCount']));
            usleep(1_000_000);
            $retry = $client->post('/api/payments/' . rawurlencode($scenario['id']) . '/retry');
            if ($retry->getStatusCode() !== 202) {
                throw new RuntimeException(sprintf('Retry failed for %s: HTTP %d %s', $scenario['id'], $retry->getStatusCode(), (string) $retry->getBody()));
            }
        } elseif (in_array($payment['status'], ['SUCCEEDED', 'ROLLED_BACK'], true)) {
            printPayment($payment);
            break;
        }

        if (microtime(true) - $startedAt > 60) {
            throw new RuntimeException(sprintf('Timed out waiting for payment %s.', $scenario['id']));
        }
        usleep(250_000);
    }
}

function getPayment(Client $client, string $id): array
{
    $response = $client->get('/api/payments/' . rawurlencode($id));
    if ($response->getStatusCode() !== 200) {
        throw new RuntimeException(sprintf('Could not retrieve %s: HTTP %d %s', $id, $response->getStatusCode(), (string) $response->getBody()));
    }

    return json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR);
}

function printPayment(array $payment): void
{
    fwrite(STDOUT, sprintf(
        "\n%s: %s, %d attempt(s), errorType=%s\n",
        $payment['id'],
        $payment['status'],
        $payment['attemptCount'],
        $payment['errorType'] ?? 'none'
    ));
    foreach ($payment['attempts'] as $attempt) {
        fwrite(STDOUT, sprintf(
            "  try %d: %s%s\n",
            $attempt['number'],
            $attempt['outcome'],
            $attempt['errorType'] === null ? '' : ' (' . $attempt['errorType'] . ')'
        ));
    }
}
