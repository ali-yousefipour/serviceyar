<?php

declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';

use ServiceYar\Database\Connection;

$pdo = Connection::get();
$directory = dirname(__DIR__, 2) . '/database/migrations';

$files = glob($directory . '/*.sql') ?: [];
sort($files, SORT_STRING);

$pdo->exec(
    "CREATE TABLE IF NOT EXISTS migrations (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        migration VARCHAR(255) NOT NULL UNIQUE,
        applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
);

$applied = $pdo->query('SELECT migration FROM migrations')->fetchAll(PDO::FETCH_COLUMN);
$applied = array_fill_keys($applied, true);

foreach ($files as $file) {
    $name = basename($file);

    if (isset($applied[$name])) {
        echo "SKIP  {$name}\n";
        continue;
    }

    $sql = file_get_contents($file);
    if ($sql === false) {
        throw new RuntimeException("Cannot read migration: {$name}");
    }

    echo "APPLY {$name}\n";

    try {
        $pdo->beginTransaction();
        $pdo->exec($sql);
        $statement = $pdo->prepare('INSERT INTO migrations (migration) VALUES (:migration)');
        $statement->execute(['migration' => $name]);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        fwrite(STDERR, "FAILED {$name}: {$e->getMessage()}\n");
        exit(1);
    }
}

echo "Migration complete.\n";
