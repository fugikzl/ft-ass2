<?php

declare(strict_types=1);

namespace App\Infrastructure;

use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;

final class Database
{
    public static function createEntityManager(): EntityManager
    {
        $paths = [dirname(__DIR__) . '/Entity'];
        $cacheDirectory = dirname(__DIR__, 2) . '/var/cache/doctrine';
        if (!is_dir($cacheDirectory)) {
            mkdir($cacheDirectory, 0775, true);
        }
        $cache = new FilesystemAdapter('doctrine', 0, $cacheDirectory);
        $config = ORMSetup::createAttributeMetadataConfig(
            $paths,
            (getenv('APP_ENV') ?: 'dev') !== 'prod',
            'synthetic-payment',
            $cache
        );
        $proxyDirectory = $cacheDirectory . '/proxies';
        if (!is_dir($proxyDirectory)) {
            mkdir($proxyDirectory, 0775, true);
        }
        $config->setProxyDir($proxyDirectory);
        $config->setProxyNamespace('App\\Proxy');

        $connection = DriverManager::getConnection([
            'driver' => 'pdo_mysql',
            'host' => getenv('DB_HOST') ?: '127.0.0.1',
            'port' => (int) (getenv('DB_PORT') ?: 3306),
            'dbname' => getenv('DB_NAME') ?: 'payments',
            'user' => getenv('DB_USER') ?: 'payments',
            'password' => getenv('DB_PASSWORD') ?: 'payments',
            'charset' => 'utf8mb4',
        ], $config);

        return new EntityManager($connection, $config);
    }
}
