<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/database.php';

function e(?string $value): string {
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}
function input_string(array $source, string $key, string $default = ''): string {
    $value = $source[$key] ?? $default;
    return is_string($value) ? $value : $default;
}
function input_int(array $source, string $key, int $default = 0): int {
    $value = $source[$key] ?? $default;
    return is_scalar($value) && filter_var($value, FILTER_VALIDATE_INT) !== false ? (int)$value : $default;
}
function redirect(string $path): void {
    header('Location: ' . BASE_URL . '/' . ltrim($path, '/'));
    exit;
}
function csrf_token(): string {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}
function verify_csrf(): void {
    $sent = $_POST['csrf'] ?? '';
    if (!is_string($sent) || !hash_equals(csrf_token(), $sent)) {
        http_response_code(419); exit('Invalid or expired form token.');
    }
}
function current_user(): ?array {
    static $loaded = false;
    static $user = null;
    if ($loaded) return $user;
    $loaded = true;
    $sessionUser = $_SESSION['user'] ?? null;
    if (!is_array($sessionUser) || !isset($sessionUser['user_id'])) return null;
    $stmt = db()->prepare("SELECT user_id,full_name,username,role,status FROM users WHERE user_id=? AND status='active' LIMIT 1");
    $stmt->execute([(int)$sessionUser['user_id']]);
    $user = $stmt->fetch() ?: null;
    if (!$user) {
        unset($_SESSION['user']);
        return null;
    }
    $user['role'] = normalized_role($user['role']);
    $_SESSION['user'] = $user;
    return $user;
}
function normalized_role(?string $role): ?string {
    return match ($role) {
        'admin', 'super_admin' => 'super_admin',
        'librarian', 'moderator' => 'moderator',
        default => null,
    };
}
function require_login(): void {
    if (!current_user()) redirect('login.php');
}
function require_role(array $roles): void {
    require_login();
    if (!in_array(normalized_role(current_user()['role'] ?? null), $roles, true)) {
        http_response_code(403); exit('Access denied.');
    }
}
function is_super_admin(): bool {
    return normalized_role(current_user()['role'] ?? null) === 'super_admin';
}
function audit_log(string $action, ?string $description = null): void {
    $user = current_user();
    if (!$user) return;
    $stmt = db()->prepare('INSERT INTO audit_logs(user_id,action,description) VALUES(?,?,?)');
    $stmt->execute([(int)$user['user_id'], $action, $description]);
}
function flash(string $key, ?string $message = null): ?string {
    if ($message !== null) { $_SESSION['flash'][$key] = $message; return null; }
    $value = $_SESSION['flash'][$key] ?? null;
    unset($_SESSION['flash'][$key]);
    return $value;
}
