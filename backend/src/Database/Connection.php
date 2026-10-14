<?php

declare(strict_types=1);

namespace ServiceYar\Database;

use PDO;
use RuntimeException;

final class Connection
{
    private static ?PDO $pdo = null;

    public static function get(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }

        $host = (string) config('DB_HOST', 'localhost');
        $port = (string) config('DB_PORT', '3306');
        $database = (string) config('DB_DATABASE', 'challi_ali');
        $username = (string) config('DB_USERNAME', $database);
        $password = (string) config('DB_PASSWORD', '');

        try {
            self::$pdo = new PDO(
                "mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4",
                $username,
                $password,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]
            );
        } catch (\PDOException $e) {
            // Log the actual MySQL/PDO reason without logging credentials or the DSN.
            // The public API deliberately keeps returning a generic database error.
            $errorInfo = is_array($e->errorInfo ?? null) ? $e->errorInfo : [];
            $sqlState = (string) ($errorInfo[0] ?? $e->getCode() ?? 'unknown');
            $driverCode = isset($errorInfo[1]) ? (string) $errorInfo[1] : 'unknown';
            $driverMessage = isset($errorInfo[2]) ? (string) $errorInfo[2] : $e->getMessage();

            // Guard against accidental credential disclosure if a driver embeds
            // a username/password-like value in its error message.
            foreach ([$password, $username] as $sensitive) {
                if ($sensitive !== '') {
                    $driverMessage = str_replace($sensitive, '[REDACTED]', $driverMessage);
                }
            }
            $driverMessage = preg_replace('/[\r\n\t]+/', ' ', $driverMessage) ?? 'unavailable';

            error_log(sprintf(
                '[ServiceYar][DB_CONNECT] host=%s port=%s database=%s username=%s sqlstate=%s driver_code=%s message=%s',
                preg_replace('/[^A-Za-z0-9._:-]/', '?', $host) ?? '?',
                preg_replace('/[^0-9]/', '', $port) ?? '',
                preg_replace('/[^A-Za-z0-9_$-]/', '?', $database) ?? '?',
                preg_replace('/[^A-Za-z0-9_$-]/', '?', $username) ?? '?',
                preg_replace('/[^A-Za-z0-9_-]/', '?', $sqlState) ?? 'unknown',
                preg_replace('/[^0-9]/', '', $driverCode) ?? 'unknown',
                $driverMessage
            ));

            throw new RuntimeException('Database connection failed.', 0, $e);
        }

        return self::$pdo;
    }
}
