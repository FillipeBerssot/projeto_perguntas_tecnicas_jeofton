<?php
function h(string $v): String {return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); }

function redirect(string $path): never {
    header("Location: {$path}");
    exit;
}

function ensure_session_started(): void {
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
}

function csrf_token(): string {
    ensure_session_started();
    if (empty($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf'];
}
function csrf_field(): string {
    return '<input type="hidden" name="_csrf" value="'.h(csrf_token()).'">';
}
function csrf_verify(): void {
    ensure_session_started();
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $t = $_POST['_csrf'] ?? '';
        if (!$t || !hash_equals($_SESSION['_csrf'] ?? '', $t)) {
            http_response_code(400);
            echo "Falha de verificação CSRF.";
            exit;
        }
    }
}