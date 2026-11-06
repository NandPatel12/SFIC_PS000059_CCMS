<?php
namespace App\Services;

use App\Database;
use PDO;

class AuthService {
    public static function startSession(): void {
        if (session_status() === PHP_SESSION_NONE) {
            ini_set('session.cookie_httponly', '1');
            ini_set('session.use_only_cookies', '1');
            session_start();
        }
    }

    public static function generateCsrfToken(): string {
        self::startSession();
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    public static function validateCsrfToken(?string $token): bool {
        self::startSession();
        return !empty($token) && !empty($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }

    public static function authenticate(string $username, string $password): ?array {
        $db = Database::getConnection();
        $stmt = $db->prepare("
            SELECT u.id, u.username, u.email, u.full_name, u.password_hash, u.status, r.slug as role_slug, r.name as role_name 
            FROM users u
            JOIN roles r ON u.role_id = r.id
            WHERE (u.username = :login OR u.email = :login) AND u.status = 'active'
            LIMIT 1
        ");
        $stmt->execute([':login' => $username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            self::startSession();
            session_regenerate_id(true);
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['role_slug'] = $user['role_slug'];
            $_SESSION['role_name'] = $user['role_name'];

            // Log Audit
            self::logAudit($user['id'], 'USER_LOGIN', 'users', (string)$user['id'], ['ip' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1']);

            // Remove hash before returning
            unset($user['password_hash']);
            return $user;
        }
        return null;
    }

    public static function getCurrentUser(): ?array {
        self::startSession();
        if (!empty($_SESSION['user_id'])) {
            return [
                'id' => $_SESSION['user_id'],
                'username' => $_SESSION['username'],
                'full_name' => $_SESSION['full_name'],
                'role_slug' => $_SESSION['role_slug'],
                'role_name' => $_SESSION['role_name']
            ];
        }
        return null;
    }

    public static function logout(): void {
        self::startSession();
        if (!empty($_SESSION['user_id'])) {
            self::logAudit($_SESSION['user_id'], 'USER_LOGOUT', 'users', (string)$_SESSION['user_id'], []);
        }
        $_SESSION = [];
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        session_destroy();
    }

    public static function logAudit(?int $userId, string $action, string $targetType, string $targetId, array $details): void {
        try {
            $db = Database::getConnection();
            $stmt = $db->prepare("
                INSERT INTO audit_logs (user_id, action, target_type, target_id, details_json, ip_address, user_agent, created_at)
                VALUES (:user_id, :action, :target_type, :target_id, :details_json, :ip_address, :user_agent, datetime('now'))
            ");
            $stmt->execute([
                ':user_id' => $userId,
                ':action' => $action,
                ':target_type' => $targetType,
                ':target_id' => $targetId,
                ':details_json' => json_encode($details),
                ':ip_address' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
                ':user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'CLI/System'
            ]);
        } catch (\Exception $e) {
            // Silently fallback without crashing critical request
        }
    }
}

