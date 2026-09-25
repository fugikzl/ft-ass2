<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use Doctrine\DBAL\DriverManager;

$connection = DriverManager::getConnection([
    'driver' => 'pdo_mysql',
    'host' => getenv('DB_HOST') ?: '127.0.0.1',
    'port' => (int) (getenv('DB_PORT') ?: 3306),
    'dbname' => getenv('DB_NAME') ?: 'payments',
    'user' => getenv('DB_USER') ?: 'payments',
    'password' => getenv('DB_PASSWORD') ?: 'payments',
    'charset' => 'utf8mb4',
]);

$connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS payments (
    id VARCHAR(64) NOT NULL,
    amount INT NOT NULL,
    failure_type VARCHAR(32) NOT NULL,
    status VARCHAR(24) NOT NULL,
    attempt_count INT NOT NULL DEFAULT 0,
    latest_error_type VARCHAR(32) DEFAULT NULL,
    processed_at DATETIME(6) DEFAULT NULL,
    created_at DATETIME(6) NOT NULL,
    updated_at DATETIME(6) NOT NULL,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

$connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS payment_attempts (
    id INT NOT NULL AUTO_INCREMENT,
    payment_id VARCHAR(64) NOT NULL,
    number INT NOT NULL,
    outcome VARCHAR(16) NOT NULL,
    error_type VARCHAR(32) DEFAULT NULL,
    created_at DATETIME(6) NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_payment_attempt_number (payment_id, number),
    CONSTRAINT fk_payment_attempt_payment FOREIGN KEY (payment_id)
        REFERENCES payments (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

fwrite(STDOUT, "Database schema is ready.\n");
