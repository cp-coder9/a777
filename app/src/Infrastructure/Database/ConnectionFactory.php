<?php

declare(strict_types=1);

namespace Architex\Infrastructure\Database;

use Architex\Infrastructure\Config\AppConfig;
use PDO;
use PDOException;
use RuntimeException;

final class ConnectionFactory
{
    public static function create(AppConfig $config): PDO
    {
        try {
            $pdo = new PDO(
                sprintf(
                    'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
                    $config->dbHost,
                    $config->dbPort,
                    $config->dbName,
                ),
                $config->dbUser,
                $config->dbPassword,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                    PDO::ATTR_STRINGIFY_FETCHES => false,
                ],
            );
            $pdo->exec("SET SESSION time_zone = '+00:00'");

            return $pdo;
        } catch (PDOException $exception) {
            throw new RuntimeException('Database connection failed.', 0, $exception);
        }
    }
}
