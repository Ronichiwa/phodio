<?php
/**
 * Phodio database connection for Supabase PostgreSQL.
 *
 * Set DATABASE_URL in Vercel. For local development you may also use
 * DB_HOST, DB_PORT, DB_NAME, DB_USER and DB_PASSWORD.
 */

if (session_status() === PHP_SESSION_NONE) {
    // Vercel's PHP runtime has an ephemeral filesystem. /tmp is writable,
    // but sessions are not guaranteed to survive a cold start.
    ini_set('session.save_path', '/tmp/phodio-sessions');
    if (!is_dir('/tmp/phodio-sessions')) {
        @mkdir('/tmp/phodio-sessions', 0700, true);
    }
}

date_default_timezone_set('Asia/Manila');

final class PhodioDbResult
{
    private array $rows;
    private int $position = 0;

    public int $num_rows;

    public function __construct(array $rows)
    {
        $this->rows = $rows;
        $this->num_rows = count($rows);
    }

    public function fetch_assoc(): ?array
    {
        if ($this->position >= count($this->rows)) {
            return null;
        }
        return $this->rows[$this->position++];
    }

    public function fetch_all(int $mode = 1): array
    {
        return $this->rows;
    }
}

final class PhodioDbStatement
{
    private PhodioDbConnection $connection;
    private string $sql;
    private ?PDOStatement $statement = null;
    private array $bound = [];
    private string $types = '';

    public function __construct(PhodioDbConnection $connection, string $sql)
    {
        $this->connection = $connection;
        $this->sql = $sql;
    }

    public function bind_param(string $types, &...$values): bool
    {
        $this->types = $types;
        $this->bound = [];
        foreach ($values as $index => &$value) {
            $this->bound[$index + 1] =& $value;
        }
        return true;
    }

    public function execute(): bool
    {
        $sql = trim($this->sql);
        // PostgreSQL does not expose MySQL's insert_id. Add RETURNING id so
        // the existing application can keep using $conn->insert_id.
        if (preg_match('/^INSERT\s+INTO\s+/i', $sql) && !preg_match('/\bRETURNING\b/i', $sql)) {
            $sql .= ' RETURNING id';
        }

        try {
            $this->statement = $this->connection->pdo()->prepare($sql);
            $params = [];
            foreach ($this->bound as $position => &$value) {
                $type = $this->types[$position - 1] ?? 's';
                if ($value === null) {
                    $params[$position] = null;
                } elseif ($type === 'i') {
                    $params[$position] = (int) $value;
                } elseif ($type === 'd') {
                    $params[$position] = (float) $value;
                } else {
                    $params[$position] = (string) $value;
                }
            }
            $ok = $this->statement->execute($params);

            if ($ok && preg_match('/^INSERT\s+INTO\s+/i', $sql)) {
                $id = $this->statement->fetchColumn();
                if ($id !== false && $id !== null) {
                    $this->connection->setInsertId((int) $id);
                }
            }
            $this->connection->clearError();
            return $ok;
        } catch (Throwable $error) {
            $this->connection->captureError($error);
            throw $error;
        }
    }

    public function get_result(): PhodioDbResult
    {
        if (!$this->statement) {
            throw new RuntimeException('The statement has not been executed.');
        }
        $rows = $this->statement->fetchAll(PDO::FETCH_ASSOC);
        return new PhodioDbResult($rows);
    }
}

final class PhodioDbConnection
{
    private PDO $pdo;
    public string $connect_error = '';
    public string $error = '';
    public int $errno = 0;
    public int $insert_id = 0;

    public function __construct()
    {
        try {
            $url = getenv('DATABASE_URL') ?: ($_ENV['DATABASE_URL'] ?? '');
            if ($url !== '') {
                $dsn = $url;
                if (stripos($dsn, 'postgres://') === 0) {
                    $dsn = 'pgsql://' . substr($dsn, 11);
                }
                $parts = parse_url($dsn);
                if ($parts === false || empty($parts['host'])) {
                    throw new RuntimeException('DATABASE_URL is invalid.');
                }
                $host = $parts['host'];
                $port = $parts['port'] ?? 5432;
                $dbname = isset($parts['path']) ? ltrim($parts['path'], '/') : 'postgres';
                $user = $parts['user'] ?? 'postgres';
                $password = $parts['pass'] ?? '';
                $query = [];
                if (!empty($parts['query'])) {
                    parse_str($parts['query'], $query);
                }
                $sslmode = $query['sslmode'] ?? 'require';
            } else {
                $host = getenv('DB_HOST') ?: '127.0.0.1';
                $port = getenv('DB_PORT') ?: '5432';
                $dbname = getenv('DB_NAME') ?: 'postgres';
                $user = getenv('DB_USER') ?: 'postgres';
                $password = getenv('DB_PASSWORD') ?: '';
                $sslmode = getenv('DB_SSLMODE') ?: 'require';
            }

            $dsn = sprintf('pgsql:host=%s;port=%s;dbname=%s;sslmode=%s', $host, $port, $dbname, $sslmode);
            $this->pdo = new PDO($dsn, $user, $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
            $this->pdo->exec("SET TIME ZONE 'Asia/Manila'");
        } catch (Throwable $error) {
            $this->connect_error = $error->getMessage();
            $this->captureError($error);
            throw $error;
        }
    }

    public function pdo(): PDO
    {
        return $this->pdo;
    }

    public function prepare(string $sql): PhodioDbStatement
    {
        return new PhodioDbStatement($this, $sql);
    }

    public function query(string $sql): PhodioDbResult|bool
    {
        try {
            $trimmed = trim($sql);
            if (preg_match('/^SET\s+time_zone/i', $trimmed)) {
                $sql = "SET TIME ZONE 'Asia/Manila'";
            }
            if (preg_match('/^SELECT\b/i', $trimmed) || preg_match('/^WITH\b/i', $trimmed)) {
                $statement = $this->pdo->query($sql);
                $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
                $this->clearError();
                return new PhodioDbResult($rows);
            }
            $this->pdo->exec($sql);
            $this->clearError();
            return true;
        } catch (Throwable $error) {
            $this->captureError($error);
            throw $error;
        }
    }

    public function begin_transaction(): bool
    {
        return $this->pdo->beginTransaction();
    }

    public function commit(): bool
    {
        return $this->pdo->commit();
    }

    public function rollback(): bool
    {
        if ($this->pdo->inTransaction()) {
            return $this->pdo->rollBack();
        }
        return true;
    }

    public function set_charset(string $charset): bool
    {
        return true;
    }

    public function setInsertId(int $id): void
    {
        $this->insert_id = $id;
    }

    public function clearError(): void
    {
        $this->error = '';
        $this->errno = 0;
    }

    public function captureError(Throwable $error): void
    {
        $this->error = $error->getMessage();
        $this->errno = 0;
        if ($error instanceof PDOException && isset($error->errorInfo[1])) {
            $driverCode = (string) $error->errorInfo[1];
            // MySQL's duplicate-key code is used by the existing application
            // to select its friendly booking-conflict message.
            if (($error->errorInfo[0] ?? '') === '23505') {
                $this->errno = 1062;
            } elseif (ctype_digit($driverCode)) {
                $this->errno = (int) $driverCode;
            }
        }
    }
}

try {
    $conn = new PhodioDbConnection();
} catch (Throwable $error) {
    http_response_code(500);
    die('Database connection failed. Check the Supabase DATABASE_URL environment variable.');
}
?>
