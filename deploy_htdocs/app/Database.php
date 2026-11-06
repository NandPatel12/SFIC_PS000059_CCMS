<?php
// Database Connection & Configuration Manager
namespace App;

use PDO;
use PDOException;

class Database {
    private static ?PDO $instance = null;

    public static function getConnection(): PDO {
        if (self::$instance === null) {
            // Automatically load .env if present
            $envFile = dirname(__DIR__) . '/.env';
            if (file_exists($envFile)) {
                $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                foreach ($lines as $line) {
                    $line = trim($line);
                    if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
                        continue;
                    }
                    list($key, $val) = explode('=', $line, 2);
                    $key = trim($key);
                    $val = trim($val, " \t\n\r\0\x0B\"'");
                    putenv("{$key}={$val}");
                    $_ENV[$key] = $val;
                    $_SERVER[$key] = $val;
                }
            }

            $dbDriver = getenv('DB_CONNECTION') ?: 'sqlite';
            $dbPath = dirname(__DIR__) . '/storage/database.sqlite';
            
            try {
                if ($dbDriver === 'mysql') {
                    $host = getenv('DB_HOST') ?: '127.0.0.1';
                    $port = getenv('DB_PORT') ?: '3306';
                    $dbname = getenv('DB_DATABASE') ?: 'gandhidham_ccms';
                    $username = getenv('DB_USERNAME') ?: 'root';
                    $password = getenv('DB_PASSWORD') ?: '';
                    $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4";
                    self::$instance = new PDO($dsn, $username, $password, [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                        PDO::ATTR_EMULATE_PREPARES => false,
                        PDO::ATTR_TIMEOUT => 3
                    ]);
                } else {
                    // Default resilient SQLite mode for local offline/development verification
                    $dsn = "sqlite:" . $dbPath;
                    self::$instance = new PDO($dsn, null, null, [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
                    ]);
                    self::$instance->exec("PRAGMA foreign_keys = ON;");
                }
            } catch (PDOException $e) {
                // If remote MySQL connection fails (e.g. running locally while host firewall blocks remote access),
                // fall back seamlessly to SQLite if available so local operation continues without crash.
                if ($dbDriver === 'mysql' && file_exists($dbPath)) {
                    try {
                        $dsn = "sqlite:" . $dbPath;
                        self::$instance = new PDO($dsn, null, null, [
                            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
                        ]);
                        self::$instance->exec("PRAGMA foreign_keys = ON;");
                        return self::$instance;
                    } catch (PDOException $fallbackErr) {
                        // ignore and report original error
                    }
                }

                http_response_code(500);
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => false,
                    'error' => 'Database connection failed: ' . $e->getMessage()
                ]);
                exit;
            }
        }
        return self::$instance;
    }
}
