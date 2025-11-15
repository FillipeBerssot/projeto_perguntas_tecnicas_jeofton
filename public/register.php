<?php
$config = require __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../src/utils.php';
require_once __DIR__ . '/../src/auth.php'; // pode (ou não) definir is_logged_in, register, login
require_once __DIR__ . '/../src/db.php';   // getPDO()

/* ----------------- SHIMS (só criam se não existirem) ----------------- */

// Usa current_user() para checar login, se seu auth.php não tiver is_logged_in()
if (!function_exists('is_logged_in')) {
  function is_logged_in(): bool {
    try {
      $u = current_user();
      return !empty($u);
    } catch (Throwable $e) {
      return false;
    }
  }
}

// Login básico usando password_hash (password_verify); seta $_SESSION['user_id']
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

// Cadastro básico: impede e-mail duplicado, grava password_hash
if (!function_exists('register')) {
  function register(string $name, string $email, string $password): bool {
    $pdo = getPDO();
    try {
      // e-mail único
      $stmt = $pdo->prepare("SELECT 1 FROM users WHERE email = ?");
      $stmt->execute([$email]);
      if ($stmt->fetchColumn()) return false;

      $hash = password_hash($password, PASSWORD_DEFAULT);
      $stmt = $pdo->prepare(
        "INSERT INTO users (name, email, password_hash, created_at)
         VALUES (?, ?, ?, datetime('now'))"
      );
      return (bool)$stmt->execute([$name, $email, $hash]);
    } catch (Throwable $e) {
      return false;
    }
  }
}
/* -------------------------------------------------------------------- */

if (is_logged_in()) {
  redirect('index.php');
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_verify();

  $name     = trim($_POST['name'] ?? '');
  $email    = trim($_POST['email'] ?? '');
  $password = $_POST['password'] ?? '';
  $confirm  = $_POST['password_confirm'] ?? '';

  if ($name === '' || $email === '' || $password === '' || $confirm === '') {
    $errors[] = 'Preencha todos os campos.';
  } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'E-mail inválido.';
  } elseif (strlen($password) < 6) {
    $errors[] = 'A senha deve ter ao menos 6 caracteres.';
  } elseif ($password !== $confirm) {
    $errors[] = 'As senhas não conferem.';
  } else {
    // usa a register() do seu auth.php se existir; senão, o shim acima
    $ok = register($name, $email, $password);
    if ($ok) {
      // autologin: usa a login() do seu auth.php se existir; senão, o shim acima
      login($email, $password);
      redirect('index.php');
    } else {
      $errors[] = 'Não foi possível cadastrar. Verifique se o e-mail já está em uso.';
    }
  }
}
?>
<!doctype html>
<html lang="pt-br">
<head>
  <meta charset="utf-8">
  <title>Cadastrar — <?= h($config['app_name']) ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
<nav class="navbar navbar-expand-lg fixed-top">
  <div class="container">
    <a class="navbar-brand fw-semibold" href="index.php"><?= h($config['app_name']) ?></a>
    <div class="d-flex align-items-center">
      <a class="btn btn-outline-primary btn-sm" href="login.php">Entrar</a>
    </div>
  </div>
</nav>

<main class="container pt-navbar" style="max-width: 760px;">
  <div class="card">
    <div class="card-body p-4 p-lg-5">
      <h1 class="h3 mb-3">Criar conta</h1>
      <p class="text-muted mb-4">Leva menos de um minuto.</p>

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
          <label class="form-label">Nome</label>
          <input type="text" class="form-control" name="name" required maxlength="80" placeholder="Seu nome completo">
        </div>

        <div class="mb-3">
          <label class="form-label">E-mail</label>
          <input type="email" class="form-control" name="email" required placeholder="seu@email.com">
        </div>

        <div class="mb-3">
          <label class="form-label">Senha</label>
          <div class="input-group">
            <input id="reg-pass" type="password" class="form-control" name="password" minlength="6" required oninput="passMeter(this.value)">
            <button class="btn btn-outline-secondary" type="button" onclick="togglePass('reg-pass', this)">Mostrar</button>
          </div>
          <div class="progress mt-2" style="height:6px;">
            <div id="meter" class="progress-bar" style="width:0%"></div>
          </div>
          <small class="text-muted">Mínimo de 6 caracteres. Use letras e números.</small>
        </div>

        <div class="mb-3">
          <label class="form-label">Confirmar senha</label>
          <input id="reg-confirm" type="password" class="form-control" name="password_confirm" minlength="6" required>
        </div>

        <div class="d-grid gap-2">
          <button class="btn btn-success btn-lg">Cadastrar</button>
          <a class="btn btn-outline-secondary" href="index.php">Voltar</a>
        </div>
      </form>

      <div class="text-center mt-3">
        <span class="text-muted">Já possui conta?</span>
        <a href="login.php">Entrar</a>
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
function passMeter(v){
  let score = 0;
  if (v.length >= 6) score += 25;
  if (/[A-Z]/.test(v)) score += 25;
  if (/[0-9]/.test(v)) score += 25;
  if (/[^A-Za-z0-9]/.test(v)) score += 25;
  document.getElementById('meter').style.width = score + '%';
}
</script>
</body>
</html>