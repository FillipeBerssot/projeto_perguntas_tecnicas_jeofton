<?php
require_once __DIR__ . '/../src/utils.php';
require_once __DIR__ . '/../src/auth.php';

/**
 * Faz logout de forma simples e confiável.
 * - Se houver logout_user() no auth.php, usa ela.
 * - Caso contrário, faz o fallback manual limpando a sessão.
 */
if (function_exists('logout_user')) {
  logout_user();
} else {
  if (session_status() !== PHP_SESSION_ACTIVE) {
    @session_start();
  }
  // limpa dados da sessão
  $_SESSION = [];

  // invalida o cookie de sessão, se existir
  if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(
      session_name(), '',
      time() - 42000,
      $params['path'],
      $params['domain'],
      $params['secure'],
      $params['httponly']
    );
  }
  @session_destroy();
}

// volta para a tela de login
redirect('/login.php');
