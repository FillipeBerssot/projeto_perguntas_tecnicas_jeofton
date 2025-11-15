<?php
// src/auth.php
require_once __DIR__ . '/db.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    @session_start();
}

/**
 * Retorna o usuário logado como array ['id'=>int, 'name'=>string, 'email'=>string]
 * ou null se não houver sessão.
 */
function current_user(): ?array {
    if (session_status() !== PHP_SESSION_ACTIVE) @session_start();
    if (empty($_SESSION['user_id'])) return null;

    $uid = (int)$_SESSION['user_id'];
    if ($uid <= 0) return null;

    $pdo = getPDO();
    $stmt = $pdo->prepare("SELECT id, name, email FROM users WHERE id = ?");
    $stmt->execute([$uid]);
    $u = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$u) return null;

    return [
        'id'    => (int)$u['id'],
        'name'  => (string)$u['name'],
        'email' => (string)$u['email'],
    ];
}

/** true/false se há usuário logado */
function is_logged_in(): bool {
    return current_user() !== null;
}

/**
 * Exige login; se não houver, redireciona para /login.php.
 * Não redeclara redirect(); usa se existir, senão usa header().
 */
function require_login(): void {
    if (!is_logged_in()) {
        if (function_exists('redirect')) {
            redirect('/login.php');
        } else {
            header('Location: /login.php');
            exit;
        }
    }
}

/** Faz login por e-mail/senha (password_verify) e guarda $_SESSION['user_id'] */
function login(string $email, string $password): bool {
    $pdo = getPDO();
    if (session_status() !== PHP_SESSION_ACTIVE) @session_start();

    $stmt = $pdo->prepare("SELECT id, password_hash FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) return false;

    $ok = !empty($row['password_hash']) && password_verify($password, $row['password_hash']);
    if ($ok) {
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int)$row['id'];
        return true;
    }
    return false;
}

/** Cadastra novo usuário (e-mail único) */
function register(string $name, string $email, string $password): bool {
    $pdo = getPDO();
    // e-mail único
    $stmt = $pdo->prepare("SELECT 1 FROM users WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetchColumn()) return false;

    $hash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("
        INSERT INTO users (name, email, password_hash, created_at)
        VALUES (?, ?, ?, datetime('now'))
    ");
    return (bool)$stmt->execute([$name, $email, $hash]);
}

/** Atualiza nome e, opcionalmente, a senha */
function update_profile(int $userId, string $name, ?string $newPassword = null): bool {
    $pdo = getPDO();
    if ($newPassword !== null && $newPassword !== '') {
        $hash = password_hash($newPassword, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE users SET name = ?, password_hash = ? WHERE id = ?");
        return (bool)$stmt->execute([$name, $hash, $userId]);
    } else {
        $stmt = $pdo->prepare("UPDATE users SET name = ? WHERE id = ?");
        return (bool)$stmt->execute([$name, $userId]);
    }
}

/** Faz logout da sessão atual */
function logout_user(): void {
    if (session_status() !== PHP_SESSION_ACTIVE) @session_start();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params['path'], $params['domain'],
            $params['secure'], $params['httponly']
        );
    }
    session_destroy();
}
