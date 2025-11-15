<?php
$config = require __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../src/utils.php';
require_once __DIR__ . '/../src/auth.php'; // expõe current_user(); se já tiver is_logged_in/login, o shim não sobrescreve
require_once __DIR__ . '/../src/db.php';   // getPDO()

/* ----------------- SHIMS: criam só se não existirem ----------------- */

// is_logged_in(): usa current_user() por baixo
if (!function_exists('is_logged_in')) {
  function is_logged_in(): bool {
    try {
      $u = current_user();
      return is_array($u) && !empty($u);
    } catch (Throwable $e) {
      return false;
    }
  }
}

// login(): autentica por email/senha usando password_hash; seta $_SESSION['user_id']
if (!function_exists('login')) {
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
}
/* ------------------------------------------------------------------- */

// agora é seguro usar is_logged_in()
if (is_logged_in()) {
  redirect('index.php');
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_verify();

  $email = trim($_POST['email'] ?? '');
  $password = $_POST['password'] ?? '';

  if ($email === '' || $password === '') {
    $errors[] = 'Informe e-mail e senha.';
  } else {
    if (login($email, $password)) {
      redirect('index.php');
    } else {
      $errors[] = 'E-mail ou senha inválidos.';
    }
  }
}
?>
<!doctype html>
<html lang="pt-br">
<head>
  <meta charset="utf-8">
  <title>Entrar — <?= h($config['app_name']) ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
<nav class="navbar navbar-expand-lg fixed-top">
  <div class="container">
    <a class="navbar-brand fw-semibold" href="index.php"><?= h($config['app_name']) ?></a>
    <div class="d-flex align-items-center">
      <a class="btn btn-primary btn-sm" href="register.php">Cadastrar</a>
    </div>
  </div>
</nav>

<main class="container pt-navbar" style="max-width: 760px;">
  <div class="card">
    <div class="card-body p-4 p-lg-5">
      <h1 class="h3 mb-3">Entrar</h1>
      <p class="text-muted mb-4">Acesse sua conta para começar um novo quiz.</p>

      <?php if ($errors): ?>
        <div class="alert alert-danger">
          <?php foreach ($errors as $e): ?>
            <div><?= h($e) ?></div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <form method="post" novalidate>
        <?= csrf_field() ?>

        <div class="mb-3">
          <label class="form-label">E-mail</label>
          <input type="email" class="form-control" name="email" placeholder="seu@email.com" required>
        </div>

        <div class="mb-3">
          <label class="form-label">Senha</label>
          <div class="input-group">
            <input id="login-pass" type="password" class="form-control" name="password" minlength="6" required>
            <button class="btn btn-outline-secondary" type="button" onclick="togglePass('login-pass', this)">Mostrar</button>
          </div>
        </div>

        <div class="d-grid gap-2">
          <button class="btn btn-success btn-lg">Entrar</button>
          <a class="btn btn-outline-secondary" href="index.php">Voltar</a>
        </div>
      </form>

      <div class="text-center mt-3">
        <span class="text-muted">Ainda não tem conta?</span>
        <a href="register.php">Cadastre-se</a>
      </div>
    </div>
  </div>
</main>

<script>
function togglePass(id, btn){
  const i = document.getElementById(id);
  const is = i.type === 'password';
  i.type = is ? 'text' : 'password';
  btn.textContent = is ? 'Ocultar' : 'Mostrar';
}
</script>
</body>
</html>
