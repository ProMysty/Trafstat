<?php
/**
 * config/database.php
 * Single, shared PDO connection for the whole application.
 */

require_once __DIR__ . '/config.php';

/**
 * Returns the shared PDO instance (created once per request).
 */
function db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
        DB_HOST,
        DB_PORT,
        DB_NAME
    );

    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false, // real prepared statements
        PDO::ATTR_STRINGIFY_FETCHES  => false,
    ];

    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
    } catch (PDOException $exception) {
        // Never show raw database errors to users in production.
        error_log('[TrafStat][DB] ' . $exception->getMessage());

        http_response_code(500);

        if (APP_ENV === 'development') {
            exit('Database connection failed: '
                . htmlspecialchars($exception->getMessage(), ENT_QUOTES, 'UTF-8'));
        }

        exit('A temporary system error occurred. Please try again later.');
    }

    return $pdo;
}

/**
 * Fetch a single row. Returns null when no row matches.
 */
function db_one(string $sql, array $params = []): ?array
{
    $statement = db()->prepare($sql);
    $statement->execute($params);
    $row = $statement->fetch();
    return $row === false ? null : $row;
}

/**
 * Fetch all rows.
 */
function db_all(string $sql, array $params = []): array
{
    $statement = db()->prepare($sql);
    $statement->execute($params);
    return $statement->fetchAll();
}

/**
 * Fetch a single scalar value.
 */
function db_value(string $sql, array $params = [])
{
    $statement = db()->prepare($sql);
    $statement->execute($params);
    return $statement->fetchColumn();
}

/**
 * Execute an INSERT/UPDATE/DELETE. Returns affected row count.
 */
function db_execute(string $sql, array $params = []): int
{
    $statement = db()->prepare($sql);
    $statement->execute($params);
    return $statement->rowCount();
}