<?php

declare(strict_types=1);

$prefix = getenv('DB_PREFIX') ?: '';

if ($prefix === '') {
    exit(0);
}

if (preg_match('/^[A-Za-z0-9_]+$/', $prefix) !== 1) {
    fwrite(STDERR, "DB_PREFIX may contain only letters, numbers and underscores.\n");
    exit(1);
}

$host = getenv('DB_HOST') ?: 'database';
$port = getenv('DB_PORT') ?: '3306';
$database = getenv('DB_DATABASE') ?: 'dromos';
$username = getenv('DB_USERNAME') ?: '';
$password = getenv('DB_PASSWORD') ?: '';

$pdo = new PDO(
    sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $host, $port, $database),
    $username,
    $password,
    [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_COLUMN,
    ],
);

$statement = $pdo->prepare(
    'SELECT TABLE_NAME
     FROM information_schema.TABLES
     WHERE TABLE_SCHEMA = :database AND TABLE_TYPE = "BASE TABLE"
     ORDER BY TABLE_NAME',
);
$statement->execute(['database' => $database]);

$tables = $statement->fetchAll();
$existingTables = array_fill_keys($tables, true);
$legacyTables = array_values(array_filter(
    $tables,
    static fn (string $table): bool => ! str_starts_with($table, $prefix),
));

if ($legacyTables === []) {
    exit(0);
}

$quoteIdentifier = static fn (string $identifier): string =>
    chr(96).str_replace(chr(96), chr(96).chr(96), $identifier).chr(96);

$renames = [];

foreach ($legacyTables as $legacyTable) {
    $prefixedTable = $prefix.$legacyTable;

    if (isset($existingTables[$prefixedTable])) {
        fwrite(
            STDERR,
            sprintf(
                "Cannot rename %s because %s already exists.\n",
                $legacyTable,
                $prefixedTable,
            ),
        );
        exit(1);
    }

    $renames[] = sprintf(
        '%s TO %s',
        $quoteIdentifier($legacyTable),
        $quoteIdentifier($prefixedTable),
    );
}

$pdo->exec('SET FOREIGN_KEY_CHECKS = 0');

try {
    $pdo->exec('RENAME TABLE '.implode(', ', $renames));
} finally {
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
}

printf(
    "Applied database prefix %s to %d existing table(s).\n",
    $prefix,
    count($legacyTables),
);
