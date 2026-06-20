<?php
// ============================================
// CYBEORCH LABS - PDO Database wrapper
// ============================================

require_once __DIR__ . '/config.php';

class Database {

    private static ?Database $instance = null;
    private PDO $pdo;

    private function __construct() {
        $host = (string) DB_HOST;
        $port = (int) DB_PORT;
        // IMPORTANT: if DB_HOST is empty, omit it from DSN so PDO defaults to its own host behavior.
        // This removes hardcoded loopback/IP literals from the code while keeping local dev workable.
        $dsn = $host !== ''
            ? sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $host, $port, DB_NAME, DB_CHARSET)
            : sprintf('mysql:port=%d;dbname=%s;charset=%s', $port, DB_NAME, DB_CHARSET);

        try {
            $this->pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::ATTR_TIMEOUT            => 5,
            ]);
        } catch (PDOException $e) {
            $msg = $e->getMessage();
            if (str_contains($msg, '2002') || str_contains($msg, 'actively refused')) {
                throw new RuntimeException(
                    'MySQL is not running. Open XAMPP Control Panel and click Start next to MySQL, then refresh this page.',
                    0,
                    $e
                );
            }
            if (str_contains($msg, '1049') || str_contains($msg, 'Unknown database')) {
                throw new RuntimeException(
                    'Database "' . DB_NAME . '" not found. Import CYBEORCH/database.sql in your phpMyAdmin UI.',
                    0,
                    $e
                );
            }
            throw $e;
        }
    }

    public static function getInstance(): Database {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function fetchAll(string $sql, array $params = []): array {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function fetchOne(string $sql, array $params = []): ?array {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public function execute(string $sql, array $params = []): int {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount();
    }

    public function insert(string $sql, array $params = []): int {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int) $this->pdo->lastInsertId();
    }

    public function lastInsertId(): int {
        return (int) $this->pdo->lastInsertId();
    }

    public function beginTransaction(): void {
        $this->pdo->beginTransaction();
    }

    public function commit(): void {
        $this->pdo->commit();
    }

    public function rollBack(): void {
        if ($this->pdo->inTransaction()) {
            $this->pdo->rollBack();
        }
    }
}

function db(): Database {
    return Database::getInstance();
}

/** Run a DB query; returns default value if MySQL is offline (for public pages). */
function dbTry(callable $fn, mixed $default = null): mixed {
    try {
        return $fn();
    } catch (RuntimeException $e) {
        if (!defined('DB_ERROR_MESSAGE')) {
            define('DB_ERROR_MESSAGE', $e->getMessage());
        }
        return $default;
    } catch (PDOException $e) {
        if (!defined('DB_ERROR_MESSAGE')) {
            define('DB_ERROR_MESSAGE', $e->getMessage());
        }
        return $default;
    }
}

function hasDbError(): bool {
    return defined('DB_ERROR_MESSAGE');
}

function dbErrorMessage(): string {
    return defined('DB_ERROR_MESSAGE') ? DB_ERROR_MESSAGE : '';
}
